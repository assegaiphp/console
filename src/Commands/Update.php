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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use RuntimeException;

#[AsCommand(
  name: 'update',
  description: 'Updates your application and its dependencies. See https://update.assegaiphp.com/',
  aliases: ['u']
)]
class Update extends Command
{
  private const FIRST_PARTY_RELEASE_LINE_PACKAGES = [
    PACKAGE_NAME_CLI,
    PACKAGE_NAME_CORE,
    PACKAGE_NAME_ORM,
    'assegaiphp/auth',
    'assegaiphp/collections',
    'assegaiphp/common',
    'assegaiphp/forms',
    'assegaiphp/util',
    'assegaiphp/validation',
  ];

  private const INDEPENDENT_FIRST_PARTY_PACKAGES = [
    'assegaiphp/beanstalkd',
    'assegaiphp/rabbitmq',
  ];

  public function configure(): void
  {
    $this
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory to update', getcwd())
      ->addOption('to', null, InputOption::VALUE_REQUIRED, 'The target framework release line, for example 0.10')
      ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the update without changing workspace files or dependencies')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Approve a cross-release update without prompting');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $inspector = new Inspector($input, $output);

    if (! $inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid workspace.</error>');
      return Command::FAILURE;
    }

    try {
      $originalComposerConfig = ComposerManifest::load($workspace);
    } catch (RuntimeException) {
      $output->writeln('<error>Failed to load composer.json.</error>');
      return Command::FAILURE;
    }

    $targetConstraint = $this->resolveTargetConstraint($input, $output);

    if ($targetConstraint === false) {
      return Command::FAILURE;
    }

    [$composerConfig, $packages] = $this->buildComposerMigration(
      $workspace,
      $originalComposerConfig,
      $targetConstraint,
    );
    $sourceConstraint = $this->findRequirementConstraint($originalComposerConfig, PACKAGE_NAME_CORE);
    $plannedCoreConstraint = $this->findRequirementConstraint($composerConfig, PACKAGE_NAME_CORE) ?? $targetConstraint;
    $isCrossReleaseUpdate = $this->releaseLine($sourceConstraint) !== $this->releaseLine($plannedCoreConstraint);

    if ($isCrossReleaseUpdate || (bool) $input->getOption('dry-run')) {
      $this->renderUpdatePlan(
        $workspace,
        $sourceConstraint,
        $plannedCoreConstraint,
        $originalComposerConfig,
        $composerConfig,
        $packages,
        $output,
      );
    }

    if ((bool) $input->getOption('dry-run')) {
      $output->writeln("\n<info>Dry run complete. No files or dependencies were changed.</info>");
      return Command::SUCCESS;
    }

    if (
      $isCrossReleaseUpdate &&
      ! $this->confirmCrossReleaseUpdate($input, $output)
    ) {
      return $input->isInteractive() ? Command::SUCCESS : Command::FAILURE;
    }

    $workspaceSnapshot = $this->captureWorkspaceSnapshot($workspace, $output);

    if ($workspaceSnapshot === false) {
      return Command::FAILURE;
    }

    $output->writeln(sprintf(
      "%s%s▹▹▹▹▹%s Update in progress... ☕\n",
      Color::FG_LIGHT_BLUE->value,
      ColorFX::BLINK->value,
      Color::RESET->value
    ));

    if (Command::SUCCESS !== $this->migrateAssegaiConfig($workspace, $output)) {
      $this->restoreWorkspaceSnapshot($workspace, $workspaceSnapshot, $output);
      return Command::FAILURE;
    }

    if (! ComposerManifest::save($workspace, $composerConfig)) {
      $output->writeln('<error>Failed to update composer.json.</error>');
      $this->restoreWorkspaceSnapshot($workspace, $workspaceSnapshot, $output);
      return Command::FAILURE;
    }

    $output->writeln('<question>UPDATE</question> composer.json');

    if (Command::SUCCESS !== $this->runComposerUpgrade($workspace, $packages, $output)) {
      $this->restoreWorkspaceSnapshot($workspace, $workspaceSnapshot, $output);
      $output->writeln('<comment>Workspace manifests were restored. Run composer install if Composer changed vendor/ before failing.</comment>');
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

  protected function resolveTargetConstraint(InputInterface $input, OutputInterface $output): false|string
  {
    $requestedTarget = trim((string) ($input->getOption('to') ?? ''));

    if ($requestedTarget === '') {
      return RECOMMENDED_FRAMEWORK_RELEASE_LINE;
    }

    if (preg_match('/^\^?v?(\d+)\.(\d+)(?:\.(?:\d+|x))?$/i', $requestedTarget, $matches) !== 1) {
      $output->writeln('<error>Invalid target release line. Use a value such as 0.10 or 0.10.0.</error>');
      return false;
    }

    $targetConstraint = sprintf('^%s.%s.0', $matches[1], $matches[2]);
    $targetReleaseLine = $this->releaseLine($targetConstraint);
    $cliReleaseLine = $this->releaseLine(RECOMMENDED_FRAMEWORK_RELEASE_LINE);

    if ($targetReleaseLine !== $cliReleaseLine) {
      $output->writeln(sprintf(
        '<error>This Console release manages the %s framework line, not %s.</error>',
        $this->formatReleaseLine($cliReleaseLine),
        $this->formatReleaseLine($targetReleaseLine),
      ));
      $output->writeln(
        '<comment>Update the global CLI first with `assegai global update` or `assegai -g update`, then retry.</comment>',
      );
      return false;
    }

    return $targetConstraint;
  }

  /**
   * @param array<string, mixed> $composerConfig
   */
  protected function findRequirementConstraint(array $composerConfig, string $packageName): ?string
  {
    foreach (['require', 'require-dev'] as $section) {
      $requirements = $composerConfig[$section] ?? [];

      if (is_array($requirements) && is_string($requirements[$packageName] ?? null)) {
        return $requirements[$packageName];
      }
    }

    return null;
  }

  protected function releaseLine(?string $constraint): ?string
  {
    if ($constraint === null || preg_match('/(\d+)\.(\d+)/', $constraint, $matches) !== 1) {
      return null;
    }

    return sprintf('%s.%s', $matches[1], $matches[2]);
  }

  protected function formatReleaseLine(?string $releaseLine): string
  {
    return $releaseLine === null ? 'an unknown release line' : $releaseLine . '.x';
  }

  /**
   * @param array<string, mixed> $before
   * @param array<string, mixed> $after
   * @param string[] $packages
   */
  protected function renderUpdatePlan(
    string $workspace,
    ?string $sourceConstraint,
    string $targetConstraint,
    array $before,
    array $after,
    array $packages,
    OutputInterface $output,
  ): void
  {
    $sourceReleaseLine = $this->releaseLine($sourceConstraint);
    $targetReleaseLine = $this->releaseLine($targetConstraint);
    $advisorUrl = sprintf(
      'https://update.assegaiphp.com/?from=%s&to=%s',
      $sourceReleaseLine === null ? 'unknown' : $sourceReleaseLine . '.x',
      $targetReleaseLine === null ? 'unknown' : $targetReleaseLine . '.0',
    );

    $output->writeln(sprintf(
      "\n<info>Framework update plan: %s → %s</info>",
      $this->formatReleaseLine($sourceReleaseLine),
      $this->formatReleaseLine($targetReleaseLine),
    ));
    $output->writeln("Workspace: $workspace");

    $requirementChanges = $this->requirementChanges($before, $after);

    if ($requirementChanges === []) {
      $output->writeln('Composer requirements: no constraint changes');
    } else {
      $output->writeln('Composer requirement changes:');

      foreach ($requirementChanges as $change) {
        $output->writeln(sprintf(
          '  - %s (%s): %s → %s',
          $change['package'],
          $change['section'],
          $change['from'],
          $change['to'],
        ));
      }
    }

    $output->writeln('Composer update set: ' . implode(', ', $packages));
    $output->writeln('Composer will update composer.lock and vendor/ with all dependent packages.');
    $output->writeln('assegai.json will receive missing supported defaults.');
    $output->writeln('Application PHP source and config/auth.php will not be created or rewritten automatically.');
    $output->writeln("Commit or back up the workspace before continuing and review $advisorUrl.");
  }

  /**
   * @param array<string, mixed> $before
   * @param array<string, mixed> $after
   * @return array<int, array{section: string, package: string, from: string, to: string}>
   */
  protected function requirementChanges(array $before, array $after): array
  {
    $changes = [];

    foreach (['require', 'require-dev'] as $section) {
      $beforeRequirements = is_array($before[$section] ?? null) ? $before[$section] : [];
      $afterRequirements = is_array($after[$section] ?? null) ? $after[$section] : [];
      $packageNames = array_values(array_unique(array_merge(
        array_keys($beforeRequirements),
        array_keys($afterRequirements),
      )));
      sort($packageNames);

      foreach ($packageNames as $packageName) {
        $beforeConstraint = $beforeRequirements[$packageName] ?? null;
        $afterConstraint = $afterRequirements[$packageName] ?? null;

        if ($beforeConstraint === $afterConstraint) {
          continue;
        }

        $changes[] = [
          'section' => $section,
          'package' => (string) $packageName,
          'from' => is_string($beforeConstraint) ? $beforeConstraint : '(not required)',
          'to' => is_string($afterConstraint) ? $afterConstraint : '(removed)',
        ];
      }
    }

    return $changes;
  }

  protected function confirmCrossReleaseUpdate(InputInterface $input, OutputInterface $output): bool
  {
    if ((bool) $input->getOption('yes')) {
      return true;
    }

    if (! $input->isInteractive()) {
      $output->writeln('<error>Cross-release updates require explicit approval.</error>');
      $output->writeln('<comment>Review with --dry-run, then re-run with --yes in non-interactive environments.</comment>');
      return false;
    }

    $helper = $this->getHelper('question');

    if (! $helper instanceof QuestionHelper) {
      $output->writeln('<error>Unable to request update confirmation.</error>');
      return false;
    }

    $confirmed = (bool) $helper->ask(
      $input,
      $output,
      new ConfirmationQuestion("\nApply this cross-release update? [y/N] ", false),
    );

    if (! $confirmed) {
      $output->writeln('<comment>Update cancelled. No files or dependencies were changed.</comment>');
    }

    return $confirmed;
  }

  /**
   * @return false|array<string, array{exists: bool, contents: string}>
   */
  protected function captureWorkspaceSnapshot(string $workspace, OutputInterface $output): false|array
  {
    $snapshot = [];

    foreach (['assegai.json', 'composer.json', 'composer.lock'] as $relativeFilename) {
      $filename = Path::join($workspace, $relativeFilename);
      $exists = is_file($filename);
      $contents = $exists ? file_get_contents($filename) : '';

      if ($contents === false) {
        $output->writeln("<error>Failed to capture $relativeFilename before the update.</error>");
        return false;
      }

      $snapshot[$relativeFilename] = [
        'exists' => $exists,
        'contents' => $contents,
      ];
    }

    return $snapshot;
  }

  /**
   * @param array<string, array{exists: bool, contents: string}> $snapshot
   */
  protected function restoreWorkspaceSnapshot(string $workspace, array $snapshot, OutputInterface $output): bool
  {
    $restored = true;

    foreach ($snapshot as $relativeFilename => $state) {
      $filename = Path::join($workspace, $relativeFilename);

      if ($state['exists']) {
        if (file_put_contents($filename, $state['contents']) === false) {
          $restored = false;
        }
        continue;
      }

      if (is_file($filename) && ! unlink($filename)) {
        $restored = false;
      }
    }

    $output->writeln($restored
      ? '<comment>RESTORE</comment> workspace manifests'
      : '<error>Failed to restore one or more workspace manifests.</error>');

    return $restored;
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
   * @param array<string, mixed> $composerConfig
   * @return array{0: array<string, mixed>, 1: string[]}
   */
  protected function buildComposerMigration(
    string $workspace,
    array $composerConfig,
    string $targetConstraint,
  ): array
  {
    $composerConfig = ProjectTemplateDefaults::hydrateComposerConfig($composerConfig);

    $composerConfig = ComposerManifest::ensureRecommendedRequirement(
      $composerConfig,
      'php',
      '^' . MIN_PHP_VERSION
    );

    $composerConfig = ComposerManifest::ensureRecommendedRequirement(
      $composerConfig,
      PACKAGE_NAME_CORE,
      $targetConstraint
    );

    $packages = [PACKAGE_NAME_CORE];

    if ($this->projectUsesOrm($workspace, $composerConfig)) {
      $composerConfig = ComposerManifest::ensureRecommendedRequirement(
        $composerConfig,
        PACKAGE_NAME_ORM,
        $targetConstraint
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

    [$composerConfig, $packages] = $this->ensureDirectFirstPartyReleaseLineRequirements(
      $composerConfig,
      $packages,
      $targetConstraint,
    );
    $packages = $this->appendDirectIndependentFirstPartyPackages($composerConfig, $packages);

    return [$composerConfig, $packages];
  }

  /**
   * @param array<string, mixed> $composerConfig
   * @param string[] $packages
   * @return array{0: array<string, mixed>, 1: string[]}
   */
  protected function ensureDirectFirstPartyReleaseLineRequirements(
    array $composerConfig,
    array $packages,
    string $targetConstraint = RECOMMENDED_FRAMEWORK_RELEASE_LINE,
  ): array
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
          $targetConstraint,
          $section,
        );
        $packages[] = $packageName;
      }
    }

    return [$composerConfig, array_values(array_unique($packages))];
  }

  /**
   * @param array<string, mixed> $composerConfig
   * @param string[] $packages
   * @return string[]
   */
  protected function appendDirectIndependentFirstPartyPackages(array $composerConfig, array $packages): array
  {
    foreach (['require', 'require-dev'] as $section) {
      $requirements = $composerConfig[$section] ?? [];

      if (! is_array($requirements)) {
        continue;
      }

      foreach (self::INDEPENDENT_FIRST_PARTY_PACKAGES as $packageName) {
        if (array_key_exists($packageName, $requirements)) {
          $packages[] = $packageName;
        }
      }
    }

    return array_values(array_unique($packages));
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
