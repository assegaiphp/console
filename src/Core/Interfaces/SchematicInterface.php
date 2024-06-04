<?php

namespace Assegai\Console\Core\Interfaces;

/**
 * Interface SchematicInterface. Represents a schematic that can be built.
 *
 * @package Assegai\Console\Core\Interfaces
 */
interface SchematicInterface
{
  /**
   * Build the schematic
   *
   * @return int
   */
  public function build(): int;

  /**
   * Tear down the schematic
   *
   * @return int
   */
  public function tearDown(): int;
}