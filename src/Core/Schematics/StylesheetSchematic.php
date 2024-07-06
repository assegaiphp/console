<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Schematics\AbstractFileSchematic;

/**
 * The stylesheet schematic.
 *
 * @package Assegai\Console\Core\Schematics
 */
class StylesheetSchematic extends AbstractFileSchematic
{
  protected function getContent(): string
  {
    $filename = basename($this->getFileName());
    return "/* $filename */";
  }
}