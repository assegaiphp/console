<?php

namespace Assegai\Console\Core\Database\Enumerations;

/**
 * Class SQLiteStorageType. This class is an enumeration of the SQLite storage types.
 *
 * @package Assegai\Console\Core\Database\Enumerations
 */
enum SQLiteStorageType: string
{
  case ON_DISK = 'on-disk';
  case IN_MEMORY = 'in-memory (persistent)';
  case TEMPORARY = 'in-memory';

  /**
   * Get the array representation of the enumeration.
   *
   * @return string[] The array representation of the enumeration.
   */
  public static function toArray(): array
  {
    return [
      self::ON_DISK->value,
      self::IN_MEMORY->value,
      self::TEMPORARY->value
    ];
  }
}
