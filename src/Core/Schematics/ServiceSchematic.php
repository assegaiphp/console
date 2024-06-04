<?php

namespace Assegai\Console\Core\Schematics;

/**
 * A service schematic.
 *
 * @package Assegai\Console\Core\Schematics
 */
class ServiceSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'service';
    $this->imports = ['Assegai\Core\Attributes\Injectable'];
    $this->attributes = ['Injectable'];
  }
}