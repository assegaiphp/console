<?php

use Assegai\Console\Commands\Migration\MigrationSetup;
use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @param array<string, array<string, mixed>> $databases
 */
function createMigrationSetupWorkspace(array $databases): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('migration-setup-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        'Acme\\BlogApi\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/assegai.json', "{}\n");
  file_put_contents($workspace . '/bootstrap.php', "<?php\n");

  $config = array_to_string(['databases' => $databases]);

  if ($config === false) {
    throw new RuntimeException('Failed to create migration setup config.');
  }

  file_put_contents($workspace . '/config/secure.php', "<?php\n\nreturn $config;\n");

  return $workspace;
}

function deleteMigrationSetupWorkspace(string $directory): void
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

describe('migration:setup', function () {
  it('uses the configured datasource type without prompting', function () {
    $workspace = createMigrationSetupWorkspace([
      DatabaseType::SQLITE->value => [
        'blog_api' => [
          'path' => '.data/blog_api.sq3',
        ],
      ],
    ]);
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      $tester = new CommandTester(new MigrationSetup());
      $status = $tester->execute([ParameterKey::DB_NAME->value => 'blog_api'], ['interactive' => false]);

      expect($status)->toBe(Command::SUCCESS);
      expect($tester->getDisplay())->not->toContain('Which type of data source do you want to use?');
      expect(file_exists($workspace . '/migrations/sqlite/blog_api'))->toBeTrue();
      expect(file_exists($workspace . '/.data/blog_api.sq3'))->toBeTrue();
    } finally {
      chdir($previousWorkingDirectory);
      deleteMigrationSetupWorkspace($workspace);
    }
  });

  it('creates sqlite config for a missing datasource when the type is explicit', function () {
    $workspace = createMigrationSetupWorkspace([]);
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      $tester = new CommandTester(new MigrationSetup());
      $status = $tester->execute([
        ParameterKey::DB_NAME->value => 'blog_api',
        '--' . DatabaseType::SQLITE->value => true,
      ], ['interactive' => false]);

      $config = require $workspace . '/config/secure.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($config['databases']['sqlite']['blog_api']['path'])->toBe('.data/blog_api.sq3');
      expect(file_exists($workspace . '/migrations/sqlite/blog_api'))->toBeTrue();
      expect(file_exists($workspace . '/.data/blog_api.sq3'))->toBeTrue();
    } finally {
      chdir($previousWorkingDirectory);
      deleteMigrationSetupWorkspace($workspace);
    }
  });

  it('asks for type only when the datasource is missing and then creates config', function () {
    $workspace = createMigrationSetupWorkspace([]);
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      CliPrompt::fake([
        'select' => [DatabaseType::SQLITE->value],
      ]);

      $tester = new CommandTester(new MigrationSetup());
      $status = $tester->execute([ParameterKey::DB_NAME->value => 'blog_api'], ['interactive' => true]);
      $config = require $workspace . '/config/secure.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($config['databases']['sqlite']['blog_api']['path'])->toBe('.data/blog_api.sq3');
      expect(file_exists($workspace . '/migrations/sqlite/blog_api'))->toBeTrue();
      expect(file_exists($workspace . '/.data/blog_api.sq3'))->toBeTrue();
    } finally {
      CliPrompt::flushFake();
      chdir($previousWorkingDirectory);
      deleteMigrationSetupWorkspace($workspace);
    }
  });
});
