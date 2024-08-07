<?php

namespace Assegai\Console\Core\Migrations\Interfaces;

use Assegai\Console\Core\Migrations\Enumerations\MigrationListerType;
use PDO;
use PDOStatement;

/**
 * Interface MigratorInterface. This interface defines the methods that a migrator class should implement.
 *
 * @package Assegai\Console\Core\Migrations
 */
interface MigratorInterface
{
  /**
   * Run the migrations.
   *
   * @param int|null $runs The number of migrations to run.
   * @return int|false The number of migrations run or false if an error occurred.
   */
  public function up(?int $runs = null): int|false;

  /**
   * Rollback the migrations.
   *
   * @param int|null $rollbacks The number of migrations to rollback.
   * @return int|false The number of migrations rolled back or false if an error occurred.
   */
  public function down(?int $rollbacks = null): int|false;

  /**
   * Reset the migrations.
   *
   * @return int|false The number of migrations reset or false if an error occurred.
   */
  public function reset(): int|false;

  /**
   * Create a new migration.
   *
   * @param string $name The name of the migration.
   * @return string|false The path to the new migration file or false if an error occurred.
   */
  public function create(string $name): string|false;

  /**
   * List all the migrations.
   *
   * @return array<string>|false The list of all migrations or false if an error occurred.
   */
  public function listAll(): array|false;

  /**
   * List the migrations that have been run.
   *
   * @return array<array{migration: string, ranAt: string}>|false The list of migrations that have been run or false if an error occurred.
   */
  public function listRan(): array|false;

  /**
   * List the migrations that are yet to be run.
   *
   * @return array<string>|false The list of migrations that are yet to be run or false if an error occurred.
   */
  public function listPending(): array|false;

  /**
   * Get the last migration.
   *
   * @return string|false The last migration or false if an error occurred.
   */
  public function last(): string|false;

  /**
   * Get the next migration.
   *
   * @return string|false The next migration or false if an error occurred.
   */
  public function next(): string|false;

  /**
   * Get the migrations table name.
   *
   * @return string
   */
  public function getMigrationsDirectoryPath(): string;

  /**
   * Gets the migration lister.
   *
   * @param MigrationListerType $type The type of the migration lister.
   * @return MigrationListerInterface The migration lister.
   */
  public function getLister(MigrationListerType $type): MigrationListerInterface;

  /**
   * Get the migrations table name.
   *
   * @return string
   */
  public static function getMigrationsTableName(): string;

  /**
   * Run a query.
   *
   * @param string $query The SQL statement to prepare and execute.
   * Data inside the query should be properly escaped.
   * @param int $fetchMode The fetch mode must be one of the PDO::FETCH_* constants.
   * @param mixed ...$fetch_mode_args Arguments of m class constructor when the mode parameter is set to PDO::FETCH_CLASS.
   * @return PDOStatement|false PDO::query returns a PDOStatement object, or FALSE on failure.
   */
  public function query(string $query, int $fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE, mixed ...$fetch_mode_args): PDOStatement|false;
}