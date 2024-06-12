<?php

namespace Assegai\Console\Core\Database\Interfaces;

use PDO;

interface DatabaseConnectionInterface
{
  /**
   * Check if the database exists.
   *
   * @param string $name The name of the database.
   * @return bool True if the database exists, false otherwise.
   */
  public static function exists(string $name): bool;

  /**
   * Check if the database does not exist.
   *
   * @param string $name The name of the database.
   * @return bool True if the database does not exist, false otherwise.
   */
  public static function doesNotExist(string $name): bool;

  /**
   * Create the database.
   *
   * @return int The status of the creation.
   */
  public static function setup(?string $name = null): int;

  /**
   * Drop the database.
   *
   * @return int The status of the drop.
   */
  public function drop(): int;

  /**
   * Gets the name of the migrations table.
   *
   * @return string The name of the migrations table.
   */
  public static function getMigrationsTableName(): string;
}