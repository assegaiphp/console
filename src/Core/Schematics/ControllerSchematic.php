<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Util\Text;

class ControllerSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'controller';
    $this->imports = ['Assegai\Core\Attributes\Controller'];
    $this->attributes = ["Controller('$this->name')"];
  }

  public function forAppModuleUpdate(): array
  {
    return [
      'use' => [],
      'declare' => [],
      'provide' => [],
      'control' => [$this->getClassName() . '::class'],
      'import' => [],
      'export' => [],
      'config' => [],
    ];
  }
}