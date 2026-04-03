<?php

use Assegai\Console\Commands\Migration\MigrationDown;
use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Migrations\MySQLDatabaseMigrator;
use Assegai\Console\Core\Migrations\PostgreSQLDatabaseMigrator;
use Assegai\Console\Core\Migrations\SQLiteDatabaseMigrator;

it('selects the sqlite migrator class for sqlite rollback operations', function () {
  $command = new class extends MigrationDown {
    public function exposeMigratorClass(DatabaseType $databaseType): string
    {
      return $this->getMigratorClass($databaseType);
    }
  };

  expect($command->exposeMigratorClass(DatabaseType::SQLITE))
    ->toBe(SQLiteDatabaseMigrator::class);
});

it('selects the postgresql migrator class for postgresql rollback operations', function () {
  $command = new class extends MigrationDown {
    public function exposeMigratorClass(DatabaseType $databaseType): string
    {
      return $this->getMigratorClass($databaseType);
    }
  };

  expect($command->exposeMigratorClass(DatabaseType::POSTGRESQL))
    ->toBe(PostgreSQLDatabaseMigrator::class);
});

it('keeps the mysql migrator class for mysql rollback operations', function () {
  $command = new class extends MigrationDown {
    public function exposeMigratorClass(DatabaseType $databaseType): string
    {
      return $this->getMigratorClass($databaseType);
    }
  };

  expect($command->exposeMigratorClass(DatabaseType::MYSQL))
    ->toBe(MySQLDatabaseMigrator::class);
});
