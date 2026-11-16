<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

enum ReleasePurpose: string
{
  case CurrentTrain = 'current-train';
  case PublishedCompatibility = 'published-compatibility';
}
