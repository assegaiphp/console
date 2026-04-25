<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\Packages\InstalledPackageExtensionLoader;
use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\ProjectTemplateDefaults;
use Assegai\Console\Util\ComposerManifest;
use Assegai\Console\Util\Enumerations\Color;
use Assegai\Console\Util\Enumerations\ColorFX;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;

#[AsCommand(
  name: 'update',
  description: 'Updates your application and its dependencies. See https://update.assegaiphp.com/',
  aliases: ['u']
)]
class Update extends Command
{
  private const FIRST_PARTY_RELEASE_LINE_PACKAGES = [
    PACKAGE_NAME_CORE,
    PACKAGE_NAME_ORM,
    PACKAGE_NAME_EVENTS,
    'assegaiphp/auth',
    'assegaiphp/beanstalkd',
    'assegaiphp/collections',
    'assegaiphp/common',
    'assegaiphp/forms',
    'assegaiphp/rabbitmq',
    'assegaiphp/util',
    'assegaiphp/validation',
  ];

  public function configure(): void
  {
    $this->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory to update', getcwd());
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $inspector = new Inspector($input, $output);

    if (! $inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid workspace.</error>');
      return Command::FAILURE;
    }

    $output->writeln(sprintf(
      "%s%s▹▹▹▹▹%s Update in progress... ☕\n",
      Color::FG_LIGHT_BLUE->value,
      ColorFX::BLINK->value,
      Color::RESET->value
    ));

    if (Command::SUCCESS !== $this->migrateAssegaiConfig($workspace, $output)) {
      return Command::FAILURE;
    }

    $packages = $this->migrateComposerConfig($workspace, $output);

    if ($packages === false) {
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->runComposerUpgrade($workspace, $packages, $output)) {
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->applyInstalledPackageIntegrations($workspace, $input, $output)) {
      return Command::FAILURE;
    }

    $packageManager = $this->detectFrontendPackageManager($workspace);

    if ($packageManager !== null && Command::SUCCESS !== $this->runFrontendInstall($workspace, $packageManager, $output)) {
      return Command::FAILURE;
    }

    $output->writeln("\n✔️ Update complete! \n");

    return Command::SUCCESS;
  }

  protected function migrateAssegaiConfig(string $workspace, OutputInterface $output): int
  {
    $filename = Path::join($workspace, 'assegai.json');
    $existingConfig = json_decode(file_get_contents($filename) ?: '', true);

    if (! is_array($existingConfig)) {
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

  /**
   * @return false|string[]
   */
  protected function migrateComposerConfig(string $workspace, OutputInterface $output): false|array
  {
    try {
      $composerConfig = ComposerManifest::load($workspace);
    } catch (RuntimeException) {
      $output->writeln('<error>Failed to load composer.json.</error>');
      return false;
    }

    $composerConfig = ProjectTemplateDefaults::hydrateComposerConfig($composerConfig);

    $composerConfig = ComposerManifest::ensureRecommendedRequirement(
      $composerConfig,
      'php',
      '^' . MIN_PHP_VERSION
    );

    $composerConfig = ComposerManifest::ensureRecommendedRequirement(
      $composerConfig,
      PACKAGE_NAME_CORE,
      RECOMMENDED_CORE_VERSION_CONSTRAINT
    );

    $packages = [PACKAGE_NAME_CORE];

    if ($this->projectUsesOrm($workspace, $composerConfig)) {
      $composerConfig = ComposerManifest::ensureRecommendedRequirement(
        $composerConfig,
        PACKAGE_NAME_ORM,
        RECOMMENDED_ORM_VERSION_CONSTRAINT
      );
      $packages[] = PACKAGE_NAME_ORM;
    }

    if ($this->projectUsesEvents($workspace, $composerConfig)) {
      $composerConfig = ComposerManifest::ensureRecommendedRequirement(
        $composerConfig,
        PACKAGE_NAME_EVENTS,
        RECOMMENDED_EVENTS_VERSION_CONSTRAINT
      );
      $packages[] = PACKAGE_NAME_EVENTS;
    }

    [$composerConfig, $packages] = $this->ensureDirectFirstPartyReleaseLineRequirements($composerConfig, $packages);

    if (! ComposerManifest::save($workspace, $composerConfig)) {
      $output->writeln('<error>Failed to update composer.json.</error>');
      return false;
    }

    $output->writeln('<question>UPDATE</question> composer.json');

    return $packages;
  }

  /**
   * @param array<string, mixed> $composerConfig
   * @param string[] $packages
   * @return array{0: array<string, mixed>, 1: string[]}
   */
  protected function ensureDirectFirstPartyReleaseLineRequirements(array $composerConfig, array $packages): array
  {
    foreach (['require', 'require-dev'] as $section) {
      $requirements = $composerConfig[$section] ?? [];

      if (! is_array($requirements)) {
        continue;
      }

      foreach (self::FIRST_PARTY_RELEASE_LINE_PACKAGES as $packageName) {
        if (! array_key_exists($packageName, $requirements)) {
          continue;
        }

        $composerConfig = ComposerManifest::ensureRecommendedRequirement(
          $composerConfig,
          $packageName,
          RECOMMENDED_FRAMEWORK_RELEASE_LINE,
          $section,
        );
        $packages[] = $packageName;
      }
    }

    return [$composerConfig, array_values(array_unique($packages))];
  }
  /**
   * @param string[] $packages
   */
  protected function runComposerUpgrade(string $workspace, array $packages, OutputInterface $output): int
  {
    $command = sprintf(
      'cd %s && composer update --with-all-dependencies --ansi %s',
      escapeshellarg($workspace),
      implode(' ', array_map('escapeshellarg', $packages))
    );

    if (false !== passthru($command, $statusCode) && $statusCode === 0) {
      return Command::SUCCESS;
    }

    $output->writeln('<error>Composer update failed.</error>');

    return Command::FAILURE;
  }

  protected function applyInstalledPackageIntegrations(string $workspace, InputInterface $input, OutputInterface $output): int
  {
    try {
      $composerConfig = ComposerManifest::load($workspace);
      $directPackageNames = array_keys(array_merge(
        (array) ($composerConfig['require'] ?? []),
        (array) ($composerConfig['require-dev'] ?? []),
      ));

      foreach (InstalledPackageExtensionLoader::load($workspace) as $packageExtension) {
        if (! in_array($packageExtension->packageName, $directPackageNames, true)) {
          continue;
        }

        $installer = $packageExtension->createInstaller();

        if ($installer === null) {
          continue;
        }

        $status = $installer->install(new PackageInstallContext(
          input: $input,
          output: $output,
          workspace: $workspace,
          packageName: $packageExtension->packageName,
        ));

        if ($status !== Command::SUCCESS) {
          $output->writeln(sprintf(
            '<error>Failed to apply package integration for %s.</error>',
            $packageExtension->packageName,
          ));
          return Command::FAILURE;
        }
      }
    } catch (RuntimeException $exception) {
      $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  protected function detectFrontendPackageManager(string $workspace): ?string
  {
    if (! file_exists(Path::join($workspace, 'package.json'))) {
      return null;
    }

    return match (true) {
      file_exists(Path::join($workspace, 'pnpm-lock.yaml')) => 'pnpm',
      file_exists(Path::join($workspace, 'yarn.lock')) => 'yarn',
      file_exists(Path::join($workspace, 'bun.lockb')),
      file_exists(Path::join($workspace, 'bun.lock')) => 'bun',
      default => 'npm',
    };
  }

  protected function runFrontendInstall(string $workspace, string $packageManager, OutputInterface $output): int
  {
    if (! is_installed($packageManager)) {
      $output->writeln("<error>$packageManager is required to update frontend dependencies.</error>");
      return Command::FAILURE;
    }

    $command = sprintf(
      'cd %s && %s install',
      escapeshellarg($workspace),
      escapeshellarg($packageManager)
    );

    if (false !== passthru($command, $statusCode) && $statusCode === 0) {
      return Command::SUCCESS;
    }

    $output->writeln("<error>$packageManager install failed.</error>");

    return Command::FAILURE;
  }

  /**
   * @param array<string, mixed> $composerConfig
   */
  protected function projectUsesOrm(string $workspace, array $composerConfig): bool
  {
    if (isset($composerConfig['require'][PACKAGE_NAME_ORM]) || isset($composerConfig['require-dev'][PACKAGE_NAME_ORM])) {
      return true;
    }

    if (is_dir(Path::join($workspace, 'migrations'))) {
      return true;
    }

    return $this->workspaceContainsAny(
      [
        Path::join($workspace, 'src'),
        Path::join($workspace, 'config'),
      ],
      [
        'Assegai\\Orm\\',
        'InjectRepository',
        "'data_source'",
        '"data_source"',
      ]
    );
  }

  /**
   * @param array<string, mixed> $composerConfig
   */
  protected function projectUsesEvents(string $workspace, array $composerConfig): bool
  {
    if (isset($composerConfig['require'][PACKAGE_NAME_EVENTS]) || isset($composerConfig['require-dev'][PACKAGE_NAME_EVENTS])) {
      return true;
    }

    if ($this->workspaceContainsAny(
      [
        Path::join($workspace, 'src'),
        Path::join($workspace, 'config'),
      ],
      [
        'Assegai\\Events\\',
        'OnEvent(',
        'EventsModule::class',
      ]
    )) {
      return true;
    }

    return false;
  }

  /**
   * @param string[] $roots
   * @param string[] $needles
   */
  protected function workspaceContainsAny(array $roots, array $needles): bool
  {
    foreach ($roots as $root) {
      if (! is_dir($root)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
      );

      foreach ($iterator as $item) {
        if (! $item->isFile()) {
          continue;
        }

        $contents = file_get_contents($item->getPathname());

        if ($contents === false) {
          continue;
        }

        foreach ($needles as $needle) {
          if (str_contains($contents, $needle)) {
            return true;
          }
        }
      }
    }

    return false;
  }
}
