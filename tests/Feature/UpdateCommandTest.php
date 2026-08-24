<?php

use Assegai\Console\Commands\Update;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @param array{composer?: array<string, mixed>, assegai?: array<string, mixed>, package_json?: bool, files?: array<string, string>} $options
 */
function createUpdateWorkspace(array $options = []): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('update-workspace-', true);

  if (! mkdir($workspace . '/src', 0755, true) && ! is_dir($workspace . '/src')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/bootstrap.php', "<?php\n");
  file_put_contents($workspace . '/composer.json', json_encode($options['composer'] ?? [
    'name' => 'acme/legacy-app',
    'autoload' => [
      'psr-4' => [
        'Acme\\Legacy\\' => 'src/',
      ],
    ],
    'require' => [
      'php' => '^8.3',
      PACKAGE_NAME_CORE => '^0.6.0',
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/assegai.json', json_encode($options['assegai'] ?? [
    'name' => 'Legacy App',
    'projectType' => 'project',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  if (($options['package_json'] ?? false) === true) {
    file_put_contents($workspace . '/package.json', json_encode([
      'name' => 'legacy-app',
      'private' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  foreach ($options['files'] ?? [] as $relativeFilename => $contents) {
    $filename = $workspace . '/' . $relativeFilename;
    $directory = dirname($filename);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
      throw new RuntimeException("Failed to create directory: $directory");
    }

    file_put_contents($filename, $contents);
  }

  return $workspace;
}

function deleteUpdateWorkspace(string $directory): void
{
  if (! is_dir($directory)) {
    return;
  }

  $items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($items as $item) {
    if ($item->isDir()) {
      rmdir($item->getPathname());
      continue;
    }

    unlink($item->getPathname());
  }

  rmdir($directory);
}

/**
 * @param string[] $aliases
 */
function installFakeUpdateWorkspacePackage(
  string $workspace,
  string $packageName,
  string $installerClass,
  string $installerSource,
  array $aliases = [],
): void
{
  $packageRoot = $workspace . '/vendor/' . $packageName;
  $sourcePath = $packageRoot . '/src/' . str_replace('\\', '/', $installerClass) . '.php';
  $sourceDirectory = dirname($sourcePath);

  if (! is_dir($sourceDirectory) && ! mkdir($sourceDirectory, 0755, true) && ! is_dir($sourceDirectory)) {
    throw new RuntimeException("Failed to create directory: $sourceDirectory");
  }

  file_put_contents($packageRoot . '/composer.json', json_encode([
    'name' => $packageName,
    'extra' => [
      'assegai' => [
        'aliases' => $aliases,
        'installer' => $installerClass,
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($sourcePath, $installerSource);

  if (! is_dir($workspace . '/vendor')) {
    mkdir($workspace . '/vendor', 0755, true);
  }

  file_put_contents($workspace . '/vendor/autoload.php', "<?php\nrequire_once " . var_export($sourcePath, true) . ";\n");
}

describe('Update command', function () {
  it('migrates legacy workspaces and upgrades the tied core and orm packages', function () {
    $workspace = createUpdateWorkspace([
      'package_json' => true,
      'files' => [
        'src/Users/UsersService.php' => <<<'PHP'
<?php

namespace Acme\Legacy\Users;

use Assegai\Orm\Attributes\InjectRepository;
PHP,
      ],
    ]);

    try {
      $command = new class extends Update {
        /** @var array<int, mixed> */
        public array $composerCalls = [];
        /** @var array<int, mixed> */
        public array $frontendCalls = [];

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerCalls[] = [
            'workspace' => $workspace,
            'packages' => $packages,
          ];

          return Command::SUCCESS;
        }

        protected function runFrontendInstall(string $workspace, string $packageManager, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->frontendCalls[] = [
            'workspace' => $workspace,
            'packageManager' => $packageManager,
          ];

          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
        '--yes' => true,
      ]);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);
      $assegaiConfig = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);

      expect($status)->toBe(Command::SUCCESS)
          ->and($composer['require']['php'])->toBe('^' . MIN_PHP_VERSION)
          ->and($composer['require'][PACKAGE_NAME_CORE])->toBe(RECOMMENDED_CORE_VERSION_CONSTRAINT)
          ->and($composer['require'][PACKAGE_NAME_ORM])->toBe(RECOMMENDED_ORM_VERSION_CONSTRAINT)
          ->and($assegaiConfig['webComponents']['hotReload']['enabled'])->toBeTrue()
          ->and($command->composerCalls[0]['packages'])->toBe([PACKAGE_NAME_CORE, PACKAGE_NAME_ORM])
          ->and($command->frontendCalls[0]['packageManager'])->toBe('npm');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('keeps non-orm workspaces on the core-only upgrade path', function () {
    $workspace = createUpdateWorkspace();

    try {
      $command = new class extends Update {
        /** @var array<int, mixed> */
        public array $composerCalls = [];
        /** @var array<int, mixed> */
        public array $frontendCalls = [];

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerCalls[] = $packages;
          return Command::SUCCESS;
        }

        protected function runFrontendInstall(string $workspace, string $packageManager, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->frontendCalls[] = $packageManager;
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
        '--yes' => true,
      ]);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);

      expect($status)->toBe(Command::SUCCESS)
          ->and($composer['require']['php'])->toBe('^' . MIN_PHP_VERSION)
          ->and($command->composerCalls[0])->toBe([PACKAGE_NAME_CORE])
          ->and($command->frontendCalls)->toBe([])
          ->and($composer['require'])->not->toHaveKey(PACKAGE_NAME_ORM);
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('updates direct first-party release-line packages that are already installed', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/legacy-app',
        'autoload' => [
          'psr-4' => [
            'Acme\\Legacy\\' => 'src/',
          ],
        ],
        'require' => [
          'php' => '^8.3',
          PACKAGE_NAME_CORE => '^0.8.0',
          'assegaiphp/auth' => '^0.8.0',
          'assegaiphp/rabbitmq' => '^1.0.0',
        ],
        'require-dev' => [
          PACKAGE_NAME_CLI => '^0.9.0',
          'assegaiphp/beanstalkd' => '^2.0.0',
          'assegaiphp/forms' => '^0.8.0',
        ],
      ],
    ]);

    try {
      $command = new class extends Update {
        /** @var array<int, mixed> */
        public array $composerCalls = [];

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerCalls[] = $packages;
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
        '--yes' => true,
      ]);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);

      expect($status)->toBe(Command::SUCCESS)
        ->and($composer['require']['php'])->toBe('^' . MIN_PHP_VERSION)
        ->and($composer['require'][PACKAGE_NAME_CORE])->toBe(RECOMMENDED_CORE_VERSION_CONSTRAINT)
        ->and($composer['require']['assegaiphp/auth'])->toBe(RECOMMENDED_FRAMEWORK_RELEASE_LINE)
        ->and($composer['require']['assegaiphp/rabbitmq'])->toBe('^1.0.0')
        ->and($composer['require-dev'][PACKAGE_NAME_CLI])->toBe(RECOMMENDED_FRAMEWORK_RELEASE_LINE)
        ->and($composer['require-dev']['assegaiphp/beanstalkd'])->toBe('^2.0.0')
        ->and($composer['require-dev']['assegaiphp/forms'])->toBe(RECOMMENDED_FRAMEWORK_RELEASE_LINE)
        ->and($command->composerCalls[0])->toBe([
          PACKAGE_NAME_CORE,
          'assegaiphp/auth',
          PACKAGE_NAME_CLI,
          'assegaiphp/forms',
          'assegaiphp/rabbitmq',
          'assegaiphp/beanstalkd',
        ]);
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });
  it('does not downgrade projects that already require a newer assegai line', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/future-app',
        'autoload' => [
          'psr-4' => [
            'Acme\\Future\\' => 'src/',
          ],
        ],
        'require' => [
          'php' => '^8.3',
          PACKAGE_NAME_CORE => '^1.0.0',
          PACKAGE_NAME_ORM => '^1.0.0',
          PACKAGE_NAME_EVENTS => '^1.0.0',
        ],
      ],
    ]);

    try {
      $command = new class extends Update {
        /** @var array<int, mixed> */
        public array $composerCalls = [];

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerCalls[] = $packages;
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
        '--yes' => true,
      ]);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);

      expect($status)->toBe(Command::SUCCESS)
        ->and($composer['require']['php'])->toBe('^' . MIN_PHP_VERSION)
        ->and($composer['require'][PACKAGE_NAME_CORE])->toBe('^1.0.0')
        ->and($composer['require'][PACKAGE_NAME_ORM])->toBe('^1.0.0')
        ->and($composer['require'][PACKAGE_NAME_EVENTS])->toBe('^1.0.0')
        ->and($command->composerCalls[0])->toBe([PACKAGE_NAME_CORE, PACKAGE_NAME_ORM, PACKAGE_NAME_EVENTS]);
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('reapplies installed orm package integration during update for older apps', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/legacy-app',
        'autoload' => [
          'psr-4' => [
            'Acme\\Legacy\\' => 'src/',
          ],
        ],
        'require' => [
          'php' => '^8.3',
          PACKAGE_NAME_CORE => '^0.7.0',
          PACKAGE_NAME_ORM => '^0.7.0',
        ],
      ],
      'files' => [
        'src/AppModule.php' => <<<'PHP'
<?php

namespace Acme\Legacy;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: []
)]
class AppModule
{
}
PHP,
        'bootstrap.php' => <<<'PHP'
<?php

use Acme\Legacy\AppModule;
use Assegai\Core\AssegaiFactory;

AssegaiFactory::createFromProject(AppModule::class, __DIR__)->run();
PHP,
      ],
    ]);

    try {
      $command = new class($workspace) extends Update {
        public function __construct(
          private readonly string $workspace,
        )
        {
          parent::__construct();
        }

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          installFakeUpdateWorkspacePackage(
            $this->workspace,
            PACKAGE_NAME_ORM,
            'Assegai\\Orm\\Assegai\\Console\\UpdateCommandOrmPackageInstaller',
            <<<'PHP'
<?php

namespace Assegai\Orm\Assegai\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class UpdateCommandOrmPackageInstaller implements PackageInstallerInterface
{
  public function install(PackageInstallContext $context): int
  {
    return RootModuleIntegrator::importModule(
      $context->workspace,
      ['Assegai\\Orm\\Assegai\\OrmModule'],
      ['OrmModule::class'],
      $context->output,
    );
  }
}
PHP,
            ['orm'],
          );

          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
        '--yes' => true,
      ]);

      $appModule = file_get_contents($workspace . '/src/AppModule.php') ?: '';

      expect($status)->toBe(Command::SUCCESS)
        ->and($appModule)->toContain('use Assegai\\Orm\\Assegai\\OrmModule;')
        ->and($appModule)->toContain('OrmModule::class');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('reapplies installed events package integration during update for older apps', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/legacy-app',
        'autoload' => [
          'psr-4' => [
            'Acme\\Legacy\\' => 'src/',
          ],
        ],
        'require' => [
          'php' => '^8.3',
          PACKAGE_NAME_CORE => '^0.7.0',
          PACKAGE_NAME_EVENTS => '^0.7.0',
        ],
      ],
      'files' => [
        'src/AppModule.php' => <<<'PHP'
<?php

namespace Acme\Legacy;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: []
)]
class AppModule
{
}
PHP,
        'bootstrap.php' => <<<'PHP'
<?php

use Acme\Legacy\AppModule;
use Assegai\Core\AssegaiFactory;

AssegaiFactory::createFromProject(AppModule::class, __DIR__)->run();
PHP,
      ],
    ]);

    try {
      $command = new class($workspace) extends Update {
        public function __construct(
          private readonly string $workspace,
        )
        {
          parent::__construct();
        }

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          installFakeUpdateWorkspacePackage(
            $this->workspace,
            PACKAGE_NAME_EVENTS,
            'Assegai\\Events\\Assegai\\Console\\UpdateCommandEventsPackageInstaller',
            <<<'PHP'
<?php

namespace Assegai\Events\Assegai\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class UpdateCommandEventsPackageInstaller implements PackageInstallerInterface
{
  public function install(PackageInstallContext $context): int
  {
    return RootModuleIntegrator::importModule(
      $context->workspace,
      ['Assegai\\Events\\Assegai\\EventsModule'],
      ['EventsModule::class'],
      $context->output,
    );
  }
}
PHP,
            ['events'],
          );

          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
        '--yes' => true,
      ]);

      $appModule = file_get_contents($workspace . '/src/AppModule.php') ?: '';

      expect($status)->toBe(Command::SUCCESS)
        ->and($appModule)->toContain('use Assegai\\Events\\Assegai\\EventsModule;')
        ->and($appModule)->toContain('EventsModule::class');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('previews a 0.9 to 0.10 update without changing the workspace', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/legacy-app',
        'autoload' => [
          'psr-4' => [
            'Acme\\Legacy\\' => 'src/',
          ],
        ],
        'require' => [
          'php' => '^8.3',
          PACKAGE_NAME_CORE => '^0.9.0',
          'assegaiphp/auth' => '^0.9.0',
        ],
        'require-dev' => [
          PACKAGE_NAME_CLI => '^0.9.0',
        ],
      ],
    ]);

    try {
      $originalComposer = file_get_contents($workspace . '/composer.json');
      $originalAssegaiConfig = file_get_contents($workspace . '/assegai.json');
      $command = new class extends Update {
        public bool $composerWasRun = false;

        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerWasRun = true;
          return Command::SUCCESS;
        }
      };
      $tester = new CommandTester($command);

      $status = $tester->execute([
        '--directory' => $workspace,
        '--to' => '0.10',
        '--dry-run' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS)
        ->and($command->composerWasRun)->toBeFalse()
        ->and(file_get_contents($workspace . '/composer.json'))->toBe($originalComposer)
        ->and(file_get_contents($workspace . '/assegai.json'))->toBe($originalAssegaiConfig)
        ->and($tester->getDisplay(true))->toContain('Framework update plan: 0.9.x → 0.10.x')
        ->toContain('assegaiphp/console (require-dev): ^0.9.0 → ^0.10.0')
        ->toContain('config/auth.php will not be created or rewritten automatically')
        ->toContain('https://update.assegaiphp.com/?from=0.9.x&to=0.10.0')
        ->toContain('Dry run complete. No files or dependencies were changed.');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('requires explicit approval for non-interactive cross-release updates', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/legacy-app',
        'require' => [
          'php' => '^8.4',
          PACKAGE_NAME_CORE => '^0.9.0',
        ],
      ],
    ]);

    try {
      $originalComposer = file_get_contents($workspace . '/composer.json');
      $tester = new CommandTester(new class extends Update {
        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          throw new RuntimeException('Composer must not run without approval.');
        }
      });

      $status = $tester->execute([
        '--directory' => $workspace,
        '--to' => '0.10',
      ], [
        'interactive' => false,
      ]);

      expect($status)->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/composer.json'))->toBe($originalComposer)
        ->and($tester->getDisplay(true))->toContain('Cross-release updates require explicit approval')
        ->toContain('Review with --dry-run, then re-run with --yes');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('restores workspace manifests when Composer fails during an update', function () {
    $workspace = createUpdateWorkspace([
      'composer' => [
        'name' => 'acme/legacy-app',
        'require' => [
          'php' => '^8.3',
          PACKAGE_NAME_CORE => '^0.9.0',
        ],
      ],
      'files' => [
        'composer.lock' => "{\n  \"content-hash\": \"before\"\n}\n",
      ],
    ]);

    try {
      $originalComposer = file_get_contents($workspace . '/composer.json');
      $originalLock = file_get_contents($workspace . '/composer.lock');
      $originalAssegaiConfig = file_get_contents($workspace . '/assegai.json');
      $tester = new CommandTester(new class extends Update {
        /**
         * @param string[] $packages
         */
        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          file_put_contents($workspace . '/composer.lock', "{\n  \"content-hash\": \"partial\"\n}\n");
          return Command::FAILURE;
        }
      });

      $status = $tester->execute([
        '--directory' => $workspace,
        '--to' => '0.10',
        '--yes' => true,
      ]);

      expect($status)->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/composer.json'))->toBe($originalComposer)
        ->and(file_get_contents($workspace . '/composer.lock'))->toBe($originalLock)
        ->and(file_get_contents($workspace . '/assegai.json'))->toBe($originalAssegaiConfig)
        ->and($tester->getDisplay(true))->toContain('RESTORE workspace manifests')
        ->toContain('Run composer install if Composer changed vendor/ before failing');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('requires the matching Console release before targeting another framework line', function () {
    $workspace = createUpdateWorkspace();

    try {
      $originalComposer = file_get_contents($workspace . '/composer.json');
      $tester = new CommandTester(new Update());
      $status = $tester->execute([
        '--directory' => $workspace,
        '--to' => '0.11',
        '--dry-run' => true,
      ]);

      expect($status)->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/composer.json'))->toBe($originalComposer)
        ->and($tester->getDisplay(true))->toContain('This Console release manages the 0.10.x framework line, not 0.11.x')
        ->toContain('assegai global update')
        ->toContain('assegai -g update');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });
});
