<?php

use Assegai\Console\Commands\Generate;
use Assegai\Console\Commands\Schematic\SchematicInit;
use Assegai\Console\Commands\Schematic\SchematicList;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

function createCustomSchematicsWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('custom-schematics-', true);

  if (! mkdir($workspace, 0755, true) && ! is_dir($workspace)) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        'Assegai\\App\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/assegai.json', json_encode([
    'name' => 'custom-schematics-test',
    'cli' => [
      'schematics' => [
        'paths' => ['schematics'],
        'discoverPackages' => true,
        'allowOverrides' => false,
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/bootstrap.php', "<?php\n");

  return $workspace;
}

function removeWorkspaceTree(string $directory): void
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

function createLocalDeclarativeSchematic(string $workspace): void
{
  $directory = $workspace . '/schematics/menu-sync/templates';
  mkdir($directory, 0755, true);

  file_put_contents($workspace . '/schematics/menu-sync/schematic.json', json_encode([
    'name' => 'menu-sync',
    'aliases' => ['ms'],
    'description' => 'Generate menu sync scaffolding.',
    'requiresWorkspace' => true,
    'kind' => 'declarative',
    'arguments' => [
      [
        'name' => 'name',
        'description' => 'The feature name to generate.',
        'required' => true,
      ],
    ],
    'options' => [],
    'templates' => [
      [
        'source' => 'templates/service.php.stub',
        'target' => '__SOURCE_ROOT__/__NAME__/__NAME__Service.php',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($directory . '/service.php.stub', <<<'PHP'
<?php

namespace __CURRENT_NAMESPACE__;

class __NAME__Service
{
}
PHP);
}

function createLocalPhpSchematic(string $workspace): void
{
  $directory = $workspace . '/schematics/customer-portal/templates';
  mkdir($directory, 0755, true);

  file_put_contents($workspace . '/schematics/customer-portal/schematic.json', json_encode([
    'name' => 'customer-portal',
    'aliases' => ['cp'],
    'description' => 'Generate customer portal scaffolding.',
    'requiresWorkspace' => true,
    'kind' => 'class',
    'arguments' => [
      [
        'name' => 'name',
        'description' => 'The feature name to generate.',
        'required' => true,
      ],
    ],
    'options' => [
      [
        'name' => 'domain',
        'shortcut' => 'D',
        'description' => 'The business domain name.',
        'acceptValue' => true,
        'valueRequired' => true,
        'default' => 'core',
      ],
    ],
    'handler' => [
      'class' => 'Assegai\\App\\Schematics\\CustomerPortalSchematic',
      'file' => 'CustomerPortalSchematic.php',
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/schematics/customer-portal/CustomerPortalSchematic.php', <<<'PHP'
<?php

namespace Assegai\App\Schematics;

use Assegai\Console\Core\Schematics\Custom\AbstractCustomSchematic;

class CustomerPortalSchematic extends AbstractCustomSchematic
{
  public function build(): int
  {
    $template = $this->loadTemplate('templates/service.php.stub');
    $content = $this->replaceTokens($template . PHP_EOL . '// Domain: __OPTION_DOMAIN__' . PHP_EOL);

    return $this->writeRelativeFile('__SOURCE_ROOT__/__NAME__/__NAME__PortalService.php', $content);
  }
}
PHP);

  file_put_contents($directory . '/service.php.stub', <<<'PHP'
<?php

namespace __CURRENT_NAMESPACE__;

class __NAME__PortalService
{
}
PHP);
}

function createPackageDeclarativeSchematic(string $workspace): void
{
  $packageRoot = $workspace . '/vendor/acme/domain-tools';
  $templatesRoot = $packageRoot . '/resources/loyalty/templates';
  mkdir($templatesRoot, 0755, true);

  file_put_contents($packageRoot . '/composer.json', json_encode([
    'name' => 'acme/domain-tools',
    'extra' => [
      'assegai' => [
        'schematics' => [
          'resources/loyalty/schematic.json',
        ],
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($packageRoot . '/resources/loyalty/schematic.json', json_encode([
    'name' => 'loyalty-program',
    'aliases' => ['lp'],
    'description' => 'Generate loyalty program scaffolding.',
    'requiresWorkspace' => true,
    'kind' => 'declarative',
    'arguments' => [
      [
        'name' => 'name',
        'description' => 'The feature name to generate.',
        'required' => true,
      ],
    ],
    'options' => [],
    'templates' => [
      [
        'source' => 'templates/service.php.stub',
        'target' => '__SOURCE_ROOT__/__NAME__/__NAME__ProgramService.php',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($templatesRoot . '/service.php.stub', <<<'PHP'
<?php

namespace __CURRENT_NAMESPACE__;

class __NAME__ProgramService
{
}
PHP);
}

describe('Schematic commands', function () {
  it('discovers custom schematics in generate help inside a workspace', function () {
    $workspace = createCustomSchematicsWorkspace();
    createLocalDeclarativeSchematic($workspace);
    $previousWorkingDirectory = getcwd();
    $previousArgv = $_SERVER['argv'] ?? null;

    try {
      if ($previousWorkingDirectory === false) {
        throw new RuntimeException('Failed to resolve the current working directory.');
      }

      chdir($workspace);
      $_SERVER['argv'] = ['assegai', 'generate', '--help'];

      $command = new Generate();

      expect($command->getHelp())->toContain('menu-sync');
    } finally {
      if ($previousWorkingDirectory !== false) {
        chdir($previousWorkingDirectory);
      }

      if ($previousArgv === null) {
        unset($_SERVER['argv']);
      } else {
        $_SERVER['argv'] = $previousArgv;
      }

      removeWorkspaceTree($workspace);
    }
  });

  it('runs a local declarative schematic', function () {
    $workspace = createCustomSchematicsWorkspace();
    createLocalDeclarativeSchematic($workspace);

    try {
      $output = new BufferedOutput();
      $status = (new Generate())->run(
        new StringInput('menu-sync orders --directory=' . escapeshellarg($workspace)),
        $output,
      );

      expect($status)->toBe(Command::SUCCESS);
      expect($workspace . '/src/Orders/OrdersService.php')->toBeFile();
      expect(file_get_contents($workspace . '/src/Orders/OrdersService.php'))
        ->toContain('namespace Assegai\App\Orders;');
    } finally {
      removeWorkspaceTree($workspace);
    }
  });

  it('runs a local class-backed schematic with a custom option', function () {
    $workspace = createCustomSchematicsWorkspace();
    createLocalPhpSchematic($workspace);

    try {
      $output = new BufferedOutput();
      $status = (new Generate())->run(
        new StringInput('customer-portal account-balance --domain=finance --directory=' . escapeshellarg($workspace)),
        $output,
      );

      $target = $workspace . '/src/AccountBalance/AccountBalancePortalService.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($target)->toBeFile();
      expect(file_get_contents($target))->toContain('// Domain: finance');
    } finally {
      removeWorkspaceTree($workspace);
    }
  });

  it('discovers package schematics from installed workspace packages', function () {
    $workspace = createCustomSchematicsWorkspace();
    createPackageDeclarativeSchematic($workspace);

    try {
      $output = new BufferedOutput();
      $status = (new Generate())->run(
        new StringInput('loyalty-program rewards --directory=' . escapeshellarg($workspace)),
        $output,
      );

      expect($status)->toBe(Command::SUCCESS);
      expect($workspace . '/src/Rewards/RewardsProgramService.php')->toBeFile();
    } finally {
      removeWorkspaceTree($workspace);
    }
  });

  it('fails cleanly when a custom schematic collides with a built-in name', function () {
    $workspace = createCustomSchematicsWorkspace();
    mkdir($workspace . '/schematics/resource/templates', 0755, true);
    file_put_contents($workspace . '/schematics/resource/schematic.json', json_encode([
      'name' => 'resource',
      'description' => 'Conflicts with the built-in resource generator.',
      'requiresWorkspace' => true,
      'kind' => 'declarative',
      'arguments' => [
        [
          'name' => 'name',
          'description' => 'The feature name to generate.',
          'required' => true,
        ],
      ],
      'templates' => [
        [
          'source' => 'templates/service.php.stub',
          'target' => '__SOURCE_ROOT__/__NAME__/__NAME__Service.php',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($workspace . '/schematics/resource/templates/service.php.stub', "<?php\n");

    try {
      $commandTester = new CommandTester(new SchematicList());
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($commandTester->getDisplay())->toContain('Schematic name collision');
    } finally {
      removeWorkspaceTree($workspace);
    }
  });

  it('scaffolds local declarative and php schematic starters', function () {
    $workspace = createCustomSchematicsWorkspace();

    try {
      $declarative = new CommandTester(new SchematicInit());
      $phpBacked = new CommandTester(new SchematicInit());

      expect($declarative->execute([
        'name' => 'menu-sync',
        '--directory' => $workspace,
      ]))->toBe(Command::SUCCESS);

      expect($workspace . '/schematics/menu-sync/schematic.json')->toBeFile();
      expect($workspace . '/schematics/menu-sync/templates/service.php.stub')->toBeFile();

      expect($phpBacked->execute([
        'name' => 'customer-portal',
        '--directory' => $workspace,
        '--php' => true,
      ]))->toBe(Command::SUCCESS);

      expect($workspace . '/schematics/customer-portal/schematic.json')->toBeFile();
      expect($workspace . '/schematics/customer-portal/templates/service.php.stub')->toBeFile();
      expect($workspace . '/schematics/customer-portal/CustomerPortalSchematic.php')->toBeFile();
    } finally {
      removeWorkspaceTree($workspace);
    }
  });

  it('lists the source type and origin for discovered schematics', function () {
    $workspace = createCustomSchematicsWorkspace();
    createLocalDeclarativeSchematic($workspace);
    createPackageDeclarativeSchematic($workspace);

    try {
      $commandTester = new CommandTester(new SchematicList());
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ]);

      $display = $commandTester->getDisplay();

      expect($status)->toBe(Command::SUCCESS);
      expect($display)->toContain('menu-sync');
      expect($display)->toContain('local');
      expect($display)->toContain('loyalty-program');
      expect($display)->toContain('package');
      expect($display)->toContain('acme/domain-tools:resources/loyalty/schematic.json');
    } finally {
      removeWorkspaceTree($workspace);
    }
  });
});
