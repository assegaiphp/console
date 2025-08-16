<?php

namespace Assegai\Console\Util\Enumerations;

/**
 * Enumeration of parameter names used in console commands.
 *
 * @package Assegai\Console\Util\Enumerations
 */
enum ParameterKey: string
{
  case DB_NAME = 'database_name';
  case DB_TYPE = 'database_type';
  case MIGRATION_NAME = 'migration_name';

  public function getShortName(): string
  {
    return match ($this) {
      self::DB_NAME => 'db',
      self::DB_TYPE => 'dt',
      self::MIGRATION_NAME => 'mn',
    };
  }
}
