<?php

namespace Assegai\Console\Core\Migrations\Enumerations;

enum MigrationListerType: string
{
  case ALL = 'all';
  case PENDING = 'pending';
  case RAN = 'ran';
}
