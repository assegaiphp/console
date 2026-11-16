<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

enum ReleaseStage: string
{
  case Prepare = 'prepare';
  case Publish = 'publish';
}
