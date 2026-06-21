<?php

use Assegai\Console\Core\Migrations\Enumerations\MigrationListerType;
use Assegai\Console\Core\Migrations\Interfaces\MigrationListerInterface;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Assegai\Console\Core\Migrations\Listers\PendingMigrationsLister;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;

it('returns pending migrations with sequential keys after filtering ran migrations', function () {
  $migrator = new class implements MigratorInterface {
    public function up(?int $runs = null): int|false
    {
      return 0;
    }

    public function down(?int $rollbacks = null): int|false
    {
      return 0;
    }

    public function reset(): int|false
    {
      return 0;
    }

    public function create(string $name): string|false
    {
      return false;
    }

    public function listAll(): array|false
    {
      return [
        '20260605214410_create_users_table',
        '20260605214415_seed_users_table',
        '20260605214420_create_posts_table',
        '20260605214430_create_posts_author_index',
      ];
    }

    public function listRan(): array|false
    {
      return [
        ['migration' => '20260605214410_create_users_table', 'ranAt' => '2026-06-08T11:27:19+00:00'],
        ['migration' => '20260605214420_create_posts_table', 'ranAt' => '2026-06-08T11:27:19+00:00'],
      ];
    }

    public function listPending(): array|false
    {
      return [];
    }

    public function last(): string|false
    {
      return false;
    }

    public function next(): string|false
    {
      return false;
    }

    public function getMigrationsDirectoryPath(): string
    {
      return '';
    }

    public function getLister(MigrationListerType $type): MigrationListerInterface
    {
      throw new RuntimeException('Not used by this test.');
    }

    public static function getMigrationsTableName(): string
    {
      return '__migrations';
    }

    public function query(string $query, int $fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE, mixed ...$fetch_mode_args): PDOStatement|false
    {
      return false;
    }
  };

  $lister = new PendingMigrationsLister($migrator, new MockInput(), new MockOutput());
  $pendingMigrations = $lister->list();

  if (false === $pendingMigrations) {
    throw new RuntimeException('Failed to list pending migrations.');
  }

  expect($pendingMigrations)->toBe([
    '20260605214415_seed_users_table',
    '20260605214430_create_posts_author_index',
  ]);
  expect(array_keys($pendingMigrations))->toBe([0, 1]);
});