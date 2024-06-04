<?php

namespace Assegai\Console\Core\Schematics;

class ModuleSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'module';
    $this->imports = ['Assegai\Core\Attributes\Modules\Module'];
    $this->attributes = ['Module()'];
  }
}