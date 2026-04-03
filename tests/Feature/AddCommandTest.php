<?php

use Assegai\Console\Commands\Add;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createAddCommandWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('add-command-', true);

  if (!mkdir($workspace, 0755, true) && !is_dir($workspace)) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  mkdir($workspace . '/src', 0755, true);

  file_put_contents($workspace . '/composer.json', json_encode([
    'name' => 'assegai/test-app',
    'autoload' => [
      'psr-4' => [
        'Assegai\\App\\' => 'src/',
      ],
    ],
    'require' => [
      'php' => '>=8.3',
      'assegaiphp/core' => '^0.7.0',
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/assegai.json', json_encode([
    'name' => 'test-app',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/bootstrap.php', <<<'PHP'
<?php

use Assegai\App\AppModule;
use Assegai\Core\AssegaiFactory;

function bootstrap(): void
{
  AssegaiFactory::create(AppModule::class)->run();
}
PHP);

  file_put_contents($workspace . '/src/AppModule.php', <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: []
)]
class AppModule
{
}
PHP);

  return $workspace;
}

function removeAddCommandWorkspace(string $directory): void
{
  if (!is_dir($directory)) {
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
function installFakeWorkspacePackage(
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

  if (!is_dir($sourceDirectory) && !mkdir($sourceDirectory, 0755, true) && !is_dir($sourceDirectory)) {
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

  if (!is_dir($workspace . '/vendor')) {
    mkdir($workspace . '/vendor', 0755, true);
  }

  file_put_contents($workspace . '/vendor/autoload.php', "<?php\nrequire_once " . var_export($sourcePath, true) . ";\n");
}

describe('Add command', function () {
  it('adds events to composer config, assegai config, and the root module when the package is already installed', function () {
    $workspace = createAddCommandWorkspace();
    installFakeWorkspacePackage(
      $workspace,
      'assegaiphp/events',
      'Assegai\\Events\\Assegai\\Console\\AddCommandEventsPackageInstaller',
      <<<'PHP'
<?php

namespace Assegai\Events\Assegai\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class AddCommandEventsPackageInstaller implements PackageInstallerInterface
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

    try {
      $tester = new CommandTester(new Add());
      $status = $tester->execute([
        'package' => 'events',
        '--directory' => $workspace,
        '--no-install' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);
      $assegai = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);
      $appModule = file_get_contents($workspace . '/src/AppModule.php') ?: '';

      expect($composer['require']['assegaiphp/events'] ?? null)->toBe('*');
      expect($assegai['events']['wildcards'] ?? null)->toBeTrue();
      expect($assegai['events']['delimiter'] ?? null)->toBe('.');
      expect(array_key_exists('maxListeners', $assegai['events'] ?? []))->toBeTrue();
      expect($appModule)->toContain('use Assegai\\Events\\Assegai\\EventsModule;');
      expect($appModule)->toContain('EventsModule::class');
    } finally {
      removeAddCommandWorkspace($workspace);
    }
  });

  it('adds orm to composer config and the root module when the package is already installed', function () {
    $workspace = createAddCommandWorkspace();
    installFakeWorkspacePackage(
      $workspace,
      'assegaiphp/orm',
      'Assegai\\Orm\\Assegai\\Console\\AddCommandOrmPackageInstaller',
      <<<'PHP'
<?php

namespace Assegai\Orm\Assegai\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class AddCommandOrmPackageInstaller implements PackageInstallerInterface
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

    try {
      $tester = new CommandTester(new Add());
      $status = $tester->execute([
        'package' => 'orm',
        '--directory' => $workspace,
        '--no-install' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);
      $appModule = file_get_contents($workspace . '/src/AppModule.php') ?: '';

      expect($composer['require']['assegaiphp/orm'] ?? null)->toBe(RECOMMENDED_ORM_VERSION_CONSTRAINT);
      expect($appModule)->toContain('use Assegai\\Orm\\Assegai\\OrmModule;');
      expect($appModule)->toContain('OrmModule::class');
    } finally {
      removeAddCommandWorkspace($workspace);
    }
  });

  it('installs and wires orm in one step when the package is missing', function () {
    $workspace = createAddCommandWorkspace();

    try {
      $command = new class($workspace) extends Add {
        public function __construct(
          private readonly string $workspace,
        ) {
          parent::__construct();
        }

        protected function runComposerInstall(string $workspace, string $packageName, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          installFakeWorkspacePackage(
            $this->workspace,
            $packageName,
            'Assegai\\Orm\\Assegai\\Console\\AddCommandInstallOrmPackageInstaller',
            <<<'PHP'
<?php

namespace Assegai\Orm\Assegai\Console;

use Assegai\Console\Core\Packages\PackageInstallContext;
use Assegai\Console\Core\Packages\PackageInstallerInterface;
use Assegai\Console\Core\Packages\RootModuleIntegrator;

class AddCommandInstallOrmPackageInstaller implements PackageInstallerInterface
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
        'package' => 'orm',
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);
      $appModule = file_get_contents($workspace . '/src/AppModule.php') ?: '';

      expect($composer['require']['assegaiphp/orm'] ?? null)->toBe(RECOMMENDED_ORM_VERSION_CONSTRAINT);
      expect($appModule)->toContain('use Assegai\\Orm\\Assegai\\OrmModule;');
      expect($appModule)->toContain('OrmModule::class');
    } finally {
      removeAddCommandWorkspace($workspace);
    }
  });

  it('fails cleanly for unsupported packages', function () {
    $workspace = createAddCommandWorkspace();

    try {
      $tester = new CommandTester(new Add());
      $status = $tester->execute([
        'package' => 'mystery',
        '--directory' => $workspace,
        '--no-install' => true,
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($tester->getDisplay())->toContain('Unsupported package');
    } finally {
      removeAddCommandWorkspace($workspace);
    }
  });
});
