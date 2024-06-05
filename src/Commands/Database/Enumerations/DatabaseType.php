<?php

namespace Assegai\Console\Commands\Database\Enumerations;

/**
 * The database type enumeration.
 *
 * @package Assegai\Console\Commands\Database\Enumerations
 */
enum DatabaseType: string
{
  case MYSQL = 'mysql';
  case POSTGRESQL = 'pgsql';
  case SQLITE = 'sqlite';
}
