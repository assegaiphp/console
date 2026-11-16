<?php

declare(strict_types=1);

namespace Assegai\Console\Util;

enum RequirementIntent: string
{
  case PreserveSupport = 'preserve-support';
  case CoordinatedUpgrade = 'coordinated-upgrade';
  case EnforceMinimum = 'enforce-minimum';
}
