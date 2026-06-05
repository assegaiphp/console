<?php

use Assegai\Console\Commands\Database\DatabaseSetup;
use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @param array<string, array<string, mixed>> $databases
 */
function createDataSourceTypeInferenceWorkspace(array $databases): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('datasource-type-inference-', true);

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
  file_put_contents($workspace . '/config/secure.php', "<?php\n\nreturn " . var_export(['databases' => $databases], true) . ";\n");

  return $workspace;
}

function deleteDataSourceTypeInferenceWorkspace(string $directory): void
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

describe('datasource type inference', function () {
  it('infers the datasource type from a configured database name argument', function () {
    $workspace = createDataSourceTypeInferenceWorkspace([
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
      $input = new MockInput([ParameterKey::DB_NAME->value => 'blog_api']);
      $type = get_datasource_type($input, new MockOutput());

      expect($type)->toBe(DatabaseType::SQLITE->value);
    } finally {
      chdir($previousWorkingDirectory);
      deleteDataSourceTypeInferenceWorkspace($workspace);
    }
  });

  it('infers the datasource type from a configured database name option', function () {
    $workspace = createDataSourceTypeInferenceWorkspace([
      DatabaseType::POSTGRESQL->value => [
        'analytics' => [
          'host' => '127.0.0.1',
        ],
      ],
    ]);
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      $input = new MockInput([], ['database' => 'analytics']);
      $type = get_datasource_type($input, new MockOutput(), ParameterKey::DB_TYPE->value, 'database');

      expect($type)->toBe(DatabaseType::POSTGRESQL->value);
    } finally {
      chdir($previousWorkingDirectory);
      deleteDataSourceTypeInferenceWorkspace($workspace);
    }
  });

  it('sets up a configured sqlite database without prompting for a datasource type', function () {
    $workspace = createDataSourceTypeInferenceWorkspace([
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
      $commandTester = new CommandTester(new DatabaseSetup());
      $status = $commandTester->execute([ParameterKey::DB_NAME->value => 'blog_api'], ['interactive' => false]);

      expect($status)->toBe(Command::SUCCESS);
      expect($commandTester->getDisplay())->not->toContain('Which type of data source do you want to use?');
      expect(file_exists($workspace . '/.data/blog_api.sq3'))->toBeTrue();
    } finally {
      chdir($previousWorkingDirectory);
      deleteDataSourceTypeInferenceWorkspace($workspace);
    }
  });

  it('does not infer a type when a configured database name is ambiguous', function () {
    $workspace = createDataSourceTypeInferenceWorkspace([
      DatabaseType::MYSQL->value => ['shared' => []],
      DatabaseType::SQLITE->value => ['shared' => ['path' => '.data/shared.sq3']],
    ]);
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      expect(get_configured_datasource_type('shared'))->toBeFalse();
    } finally {
      chdir($previousWorkingDirectory);
      deleteDataSourceTypeInferenceWorkspace($workspace);
    }
  });
});
