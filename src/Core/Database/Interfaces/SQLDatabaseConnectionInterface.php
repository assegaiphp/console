<?php

namespace Assegai\Console\Core\Database\Interfaces;

/**
 * Interface SQLDatabaseConnectionInterface. This interface is for SQL database connections.
 *
 * @package Assegai\Console\Core\Database\Interfaces
 */
interface SQLDatabaseConnectionInterface extends DatabaseConnectionInterface
{
  /**
   * Check if the database contains a table of the given name.
   *
   * @param string $tableName The name of the table to check for.
   * @return bool True if the table exists, false otherwise.
   */
  public function hasTable(string $tableName): bool;

  /**
   * Create the migrations table.
   *
   * @return int The status of the operation.
   */
  public function createMigrationsTable(): int;
}