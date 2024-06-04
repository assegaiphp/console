<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Schematics\Enumerations\ClassTemplate;

class InterfaceSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'interface';
    $this->template = ClassTemplate::INTERFACE;
  }
}