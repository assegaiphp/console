<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

enum ModuleVersioning: string
{
  case Framework = 'framework';
  case LegacyCoordinated = 'legacy-coordinated';
  case StableDependency = 'stable-dependency';
}
