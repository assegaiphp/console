<?php

use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Core\Migrations\SQLiteDatabaseMigrator;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Symfony\Component\Console\Command\Command;

function createSQLiteScriptMigrationWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('sqlite-script-migration-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  $migrationPath = $workspace . '/migrations/sqlite/blog_api/20260608100000_create_users';
  if (! mkdir($migrationPath, 0755, true) && ! is_dir($migrationPath)) {
    throw new RuntimeException("Failed to create test migration directory: $migrationPath");
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
  file_put_contents($workspace . '/config/secure.php', <<<'CONFIG'
<?php

return [
  'databases' => [
    'sqlite' => [
      'blog_api' => [
        'path' => '.data/blog_api.sq3',
      ],
    ],
  ],
];
CONFIG);
  file_put_contents($migrationPath . '/up.sql', <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users (email);

INSERT INTO users (email) VALUES ('user@example.com');
SQL);
  file_put_contents($migrationPath . '/down.sql', <<<'SQL'
DROP INDEX IF EXISTS idx_users_email;

DROP TABLE IF EXISTS users;
SQL);

  return $workspace;
}

function deleteSQLiteScriptMigrationWorkspace(string $directory): void
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

describe('SQLite migration script execution', function () {
  it('executes every statement in up and down sql files', function () {
    $workspace = createSQLiteScriptMigrationWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      expect(SQLiteDatabase::setup('blog_api'))->toBe(Command::SUCCESS);

      $migrator = new SQLiteDatabaseMigrator('blog_api', new MockInput(), new MockOutput());

      expect($migrator->up())->toBe(1);

      $connection = new PDO('sqlite:' . $workspace . '/.data/blog_api.sq3');
      $userCountStatement = $connection->query('SELECT COUNT(*) FROM users');
      $indexStatement = $connection
        ->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_users_email'");

      if (false === $userCountStatement || false === $indexStatement) {
        throw new RuntimeException('Failed to inspect the migrated SQLite database.');
      }

      expect((int) $userCountStatement->fetchColumn())->toBe(1);
      expect($indexStatement->fetchColumn())->toBe('idx_users_email');

      $userCountStatement->closeCursor();
      $indexStatement->closeCursor();
      $connection = null;

      expect($migrator->down())->toBe(1);

      $connection = new PDO('sqlite:' . $workspace . '/.data/blog_api.sq3');
      $tableStatement = $connection
        ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");

      if (false === $tableStatement) {
        throw new RuntimeException('Failed to inspect the rolled back SQLite database.');
      }

      expect($tableStatement->fetchColumn())->toBeFalse();
    } finally {
      chdir($previousWorkingDirectory);
      deleteSQLiteScriptMigrationWorkspace($workspace);
    }
  });
});