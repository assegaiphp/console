<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\Packages\FirstPartyPackageCatalog;
use Assegai\Console\Core\Packages\InstalledPackageExtension;
use Assegai\Console\Core\Packages\InstalledPackageExtensionLoader;
use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\ProjectTemplateDefaults;
use Assegai\Console\Releases\BundledRelease;
use Assegai\Console\Util\ComposerManifest;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'add',
  description: 'Adds a first-party package to the current Assegai workspace.'
)]
class Add extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('package', InputArgument::REQUIRED, 'The package name or shortcut to add.')
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd())
      ->addOption('no-install', null, InputOption::VALUE_NONE, 'Only update workspace files without running composer.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid workspace.</error>');
      return Command::FAILURE;
    }

    $requestedPackage = (string) $input->getArgument('package');
    $packageMetadata = $this->resolvePackageMetadata($requestedPackage, $workspace);

    if ($packageMetadata === null) {
      $output->writeln(
        '<error>This package has no public recommendation in this Console release inventory. Use a released first-party package such as rabbitmq. If the package was released later, update the CLI with `assegai global update`.</error>'
      );
      return Command::FAILURE;
    }

    $packageName = $packageMetadata['packageName'];

    try {
      $composerConfig = ComposerManifest::load($workspace);
      $composerConfig = ComposerManifest::ensureRecommendedRequirement($composerConfig, $packageName, $packageMetadata['constraint']);
      if (str_starts_with($packageName, 'assegaiphp/')) {
        $constraint = $composerConfig['require'][$packageName] ?? null;
        if (!is_string($constraint) || BundledRelease::inventory()->requirementIssues($packageName, $constraint) !== []) {
          throw new RuntimeException("Please review the custom or newer $packageName requirement against the coordinated release plan. No project files were changed.");
        }
      }
    } catch (RuntimeException $error) {
      $output->writeln('<error>' . $error->getMessage() . '</error>');
      return Command::FAILURE;
    }

    if (!ComposerManifest::save($workspace, $composerConfig)) {
      $output->writeln('<error>Failed to update composer.json.</error>');
      return Command::FAILURE;
    }

    $output->writeln('<question>UPDATE</question> composer.json');

    if (Command::SUCCESS !== $this->hydrateAssegaiConfig($workspace, $output)) {
      return Command::FAILURE;
    }

    $installedExtension = $this->loadInstalledPackageExtension($workspace, $packageName);
    $installWasSkipped = (bool) $input->getOption('no-install');

    if ($installedExtension === null && !$installWasSkipped) {
      if (Command::SUCCESS !== $this->runComposerInstall($workspace, $packageName, $output)) {
        return Command::FAILURE;
      }

      $installedExtension = $this->loadInstalledPackageExtension($workspace, $packageName);
    }

    if ($installWasSkipped && $installedExtension === null) {
      $output->writeln('<comment>Skipped package installer because the package is not installed in this workspace yet.</comment>');
      return Command::SUCCESS;
    }

    if ($installedExtension !== null) {
      if (Command::SUCCESS !== $this->applyWorkspaceIntegration($installedExtension, $workspace, $input, $output)) {
        return Command::FAILURE;
      }
    }

    return Command::SUCCESS;
  }

  /**
   * @return array{packageName: string, constraint: string}|null
   */
  protected function resolvePackageMetadata(string $package, string $workspace): ?array
  {
    $installedExtension = InstalledPackageExtensionLoader::resolve($workspace, $package, requireAutoload: false);

    if ($installedExtension !== null) {
      $firstPartyPackage = FirstPartyPackageCatalog::resolve($installedExtension->packageName);

      if (str_starts_with($installedExtension->packageName, 'assegaiphp/')) {
        return $firstPartyPackage;
      }

      return [
        'packageName' => $installedExtension->packageName,
        'constraint' => $firstPartyPackage['constraint'] ?? '*',
      ];
    }

    return FirstPartyPackageCatalog::resolve($package);
  }

  protected function loadInstalledPackageExtension(string $workspace, string $packageName): ?InstalledPackageExtension
  {
    return InstalledPackageExtensionLoader::find($workspace, $packageName);
  }

  private function hydrateAssegaiConfig(string $workspace, OutputInterface $output): int
  {
    $filename = Path::join($workspace, 'assegai.json');
    $existingConfig = json_decode(file_get_contents($filename) ?: '', true);

    if (!is_array($existingConfig)) {
      $output->writeln('<error>Failed to decode assegai.json.</error>');
      return Command::FAILURE;
    }

    $updatedConfig = ProjectTemplateDefaults::hydrateAssegaiConfig($existingConfig);

    if ($updatedConfig === $existingConfig) {
      return Command::SUCCESS;
    }

    if (false === file_put_contents($filename, json_encode($updatedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL)) {
      $output->writeln('<error>Failed to update assegai.json.</error>');
      return Command::FAILURE;
    }

    $output->writeln('<question>UPDATE</question> assegai.json');

    return Command::SUCCESS;
  }

  protected function applyWorkspaceIntegration(
    InstalledPackageExtension $packageExtension,
    string $workspace,
    InputInterface $input,
    OutputInterface $output,
  ): int
  {
    $installer = $packageExtension->createInstaller();

    if ($installer === null) {
      return Command::SUCCESS;
    }

    return $installer->install(new PackageInstallContext(
      input: $input,
      output: $output,
      workspace: $workspace,
      packageName: $packageExtension->packageName,
    ));
  }

  protected function runComposerInstall(string $workspace, string $packageName, OutputInterface $output): int
  {
    $command = sprintf(
      'cd %s && composer update --with-all-dependencies --ansi %s',
      escapeshellarg($workspace),
      escapeshellarg($packageName)
    );

    if (false !== passthru($command, $statusCode) && $statusCode === 0) {
      return Command::SUCCESS;
    }

    $output->writeln('<error>Composer install failed.</error>');

    return Command::FAILURE;
  }
}
