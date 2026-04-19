<?php

use Assegai\Console\Commands\Migration\MigrationDown;
use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Migrations\MariaDbDatabaseMigrator;
use Assegai\Console\Core\Migrations\MsSqlDatabaseMigrator;
use Assegai\Console\Core\Migrations\MySQLDatabaseMigrator;
use Assegai\Console\Core\Migrations\PostgreSQLDatabaseMigrator;
use Assegai\Console\Core\Migrations\SQLiteDatabaseMigrator;

class ExposedMigrationDownCommand extends MigrationDown
{
  public function exposeMigratorClass(DatabaseType $databaseType): string
  {
    return $this->getMigratorClass($databaseType);
  }
}

it('selects the sqlite migrator class for sqlite rollback operations', function () {
  $command = new ExposedMigrationDownCommand();

  expect($command->exposeMigratorClass(DatabaseType::SQLITE))
    ->toBe(SQLiteDatabaseMigrator::class);
});

it('selects the postgresql migrator class for postgresql rollback operations', function () {
  $command = new ExposedMigrationDownCommand();

  expect($command->exposeMigratorClass(DatabaseType::POSTGRESQL))
    ->toBe(PostgreSQLDatabaseMigrator::class);
});

it('keeps the mysql migrator class for mysql rollback operations', function () {
  $command = new ExposedMigrationDownCommand();

  expect($command->exposeMigratorClass(DatabaseType::MYSQL))
    ->toBe(MySQLDatabaseMigrator::class);
});

it('selects the mariadb migrator class for mariadb rollback operations', function () {
  $command = new ExposedMigrationDownCommand();

  expect($command->exposeMigratorClass(DatabaseType::MARIADB))
    ->toBe(MariaDbDatabaseMigrator::class);
});

it('selects the mssql migrator class for mssql rollback operations', function () {
  $command = new ExposedMigrationDownCommand();

  expect($command->exposeMigratorClass(DatabaseType::MSSQL))
    ->toBe(MsSqlDatabaseMigrator::class);
});
