<?php

namespace Assegai\Console\Core\Migrations\Enumerations;

/**
 * Class MigrationListerType. This class provides the types of migration listers.
 *
 * @package Assegai\Console\Core\Migrations\Enumerations
 */
enum MigrationListerType: string
{
  // The type of migration lister that lists all migrations.
  case ALL = 'all';
  // The type of migration lister that lists pending migrations.
  case PENDING = 'pending';
  // The type of migration lister that lists ran migrations.
  case RAN = 'ran';
}
