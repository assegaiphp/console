<?php

namespace Assegai\Console\Core\Database\Enumerations;

/**
 * The database type enumeration.
 *
 * @package Assegai\Console\Commands\Database\Enumerations
 */
enum DatabaseType: string
{
  case MYSQL = 'mysql';
  case MARIADB = 'mariadb';
  case POSTGRESQL = 'pgsql';
  case SQLITE = 'sqlite';
  case MSSQL = 'mssql';

  /**
   * Check if the type is valid.
   *
   * @param string $type The type to check.
   * @return bool True if the type is valid, false otherwise.
   */
  public static function isValid(string $type): bool
  {
    return in_array($type, self::toArray(), true);
  }

  /**
   * Returns the array of database types.
   *
   * @return string[] The array of database types.
   */
  public static function toArray(): array
  {
    return [
      self::MYSQL->value,
      self::MARIADB->value,
      self::POSTGRESQL->value,
      self::SQLITE->value,
      self::MSSQL->value,
    ];
  }
}
