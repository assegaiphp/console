<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\ProjectTemplateDefaults;
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

    $packageName = $this->normalizePackageName((string) $input->getArgument('package'));

    if ($packageName === null) {
      $output->writeln('<error>Unsupported package. Supported values: events.</error>');
      return Command::FAILURE;
    }

    try {
      $composerConfig = ComposerManifest::load($workspace);
    } catch (RuntimeException) {
      $output->writeln('<error>Failed to load composer.json.</error>');
      return Command::FAILURE;
    }

    $composerConfig = ComposerManifest::ensureRequirement(
      $composerConfig,
      $packageName,
      $this->resolveConstraint($packageName)
    );

    if (!ComposerManifest::save($workspace, $composerConfig)) {
      $output->writeln('<error>Failed to update composer.json.</error>');
      return Command::FAILURE;
    }

    $output->writeln('<question>UPDATE</question> composer.json');

    if (Command::SUCCESS !== $this->hydrateAssegaiConfig($workspace, $output)) {
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->applyWorkspaceIntegration($packageName, $workspace, $output)) {
      return Command::FAILURE;
    }

    if ((bool) $input->getOption('no-install')) {
      return Command::SUCCESS;
    }

    if (Command::SUCCESS !== $this->runComposerInstall($workspace, $packageName, $output)) {
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  private function normalizePackageName(string $package): ?string
  {
    return match (strtolower(trim($package))) {
      'events', PACKAGE_NAME_EVENTS => PACKAGE_NAME_EVENTS,
      default => null,
    };
  }

  private function resolveConstraint(string $packageName): string
  {
    return match ($packageName) {
      PACKAGE_NAME_EVENTS => RECOMMENDED_EVENTS_VERSION_CONSTRAINT,
      default => '*',
    };
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

  private function applyWorkspaceIntegration(string $packageName, string $workspace, OutputInterface $output): int
  {
    return match ($packageName) {
      PACKAGE_NAME_EVENTS => $this->integrateEventsModule($workspace, $output),
      default => Command::SUCCESS,
    };
  }

  private function integrateEventsModule(string $workspace, OutputInterface $output): int
  {
    $moduleFilename = $this->resolveRootModuleFilename($workspace);

    if ($moduleFilename === null) {
      $output->writeln('<comment>Skipped AppModule import update because the root module could not be detected.</comment>');
      return Command::SUCCESS;
    }

    $previousWorkingDirectory = getcwd() ?: $workspace;
    chdir($workspace);

    try {
      return update_module_file([
        'use' => [
          'Assegai\\Events\\Assegai\\EventsModule',
        ],
        'imports' => [
          'EventsModule::class',
        ],
      ], $moduleFilename, $output);
    } finally {
      chdir($previousWorkingDirectory);
    }
  }

  private function resolveRootModuleFilename(string $workspace): ?string
  {
    $bootstrapFile = Path::join($workspace, BOOTSTRAP_FILE);

    if (is_file($bootstrapFile)) {
      $contents = file_get_contents($bootstrapFile);

      if ($contents !== false && preg_match('/AssegaiFactory::create\(\s*([\\\\A-Za-z0-9_]+)::class\s*\)/', $contents, $matches)) {
        $candidate = trim($matches[1]);
        $basename = basename(str_replace('\\', '/', $candidate));

        if ($basename !== '') {
          return preg_replace('/\.php$/', '', $basename) ?: null;
        }
      }
    }

    $defaultRootModule = Path::join($workspace, 'src', 'AppModule.php');

    return is_file($defaultRootModule) ? 'AppModule' : null;
  }

  private function runComposerInstall(string $workspace, string $packageName, OutputInterface $output): int
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
