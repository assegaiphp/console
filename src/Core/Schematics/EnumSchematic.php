<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Schematics\Enumerations\ClassTemplate;

/**
 * EnumSchematic class. This class is used to generate Enum classes.
 *
 * @package Assegai\Console\Core\Schematics
 */
class EnumSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->template = ClassTemplate::ENUM;
    $this->namespaceSuffix = $this->properName;
  }
}