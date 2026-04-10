<?php

use Assegai\Console\Core\Database\SQLiteDatabase;
use Symfony\Component\Console\Command\Command;

function createSQLiteSetupWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('sqlite-setup-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        'Acme\\Demo\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/assegai.json', "{}\n");
  file_put_contents($workspace . '/bootstrap.php', "<?php\n");
  file_put_contents($workspace . '/config/default.php', <<<'PHP'
<?php

return [
  'databases' => [
    'sqlite' => [
      'cinema_db' => [
        'path' => '.data/cinema_db.sq3',
      ],
    ],
  ],
];
PHP);

  return $workspace;
}

function deleteSQLiteSetupWorkspace(string $directory): void
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

describe('SQLite database setup', function () {
  it('creates a configured on-disk sqlite database automatically', function () {
    $workspace = createSQLiteSetupWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      $status = SQLiteDatabase::setup('cinema_db');
      $databasePath = $workspace . '/.data/cinema_db.sq3';

      expect($status)->toBe(Command::SUCCESS);
      expect(file_exists($databasePath))->toBeTrue();

      $connection = new PDO('sqlite:' . $databasePath);
      $statement = $connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='__migrations'");

      expect($statement)->not->toBeFalse();
      if ($statement === false) {
        throw new RuntimeException('Failed to inspect the SQLite migrations table.');
      }

      $migrationsTable = $statement->fetchColumn();

      expect($migrationsTable)->toBe('__migrations');
    } finally {
      chdir($previousWorkingDirectory);
      deleteSQLiteSetupWorkspace($workspace);
    }
  });
});
