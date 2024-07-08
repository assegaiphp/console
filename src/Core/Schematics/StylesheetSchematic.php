<?php

namespace Assegai\Console\Core\Schematics;

use Symfony\Component\Console\Command\Command;

/**
 * The stylesheet schematic.
 *
 * @package Assegai\Console\Core\Schematics
 */
class StylesheetSchematic extends AbstractFileSchematic
{
  /**
   * @inheritDoc
   */
  protected function getContent(): string
  {
    $filename = basename($this->getFileName());
    return "/* $filename */";
  }

  /**
   * @inheritDoc
   */
  public function prepareBuild(): int
  {
    // Override this method to add custom logic before building the schematic.
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function finalizeBuild(): int
  {
    // Override this method to add custom logic before building the schematic.
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function prepareTearDown(): int
  {
    // Override this method to add custom logic before building the schematic.
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function finalizeTearDown(): int
  {
    // Override this method to add custom logic before building the schematic.
    return Command::SUCCESS;
  }
}