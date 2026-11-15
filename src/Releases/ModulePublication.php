<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

enum ModulePublication: string
{
  case Released = 'released';
  case Unreleased = 'unreleased';
}
