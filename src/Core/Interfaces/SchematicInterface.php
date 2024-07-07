<?php

namespace Assegai\Console\Core\Interfaces;

/**
 * Interface SchematicInterface. Represents a schematic that can be built.
 *
 * @package Assegai\Console\Core\Interfaces
 */
interface SchematicInterface extends ConfigurableInterface
{
  /**
   * Prepare to build the schematic
   *
   * @return int
   */
  public function prepareBuild(): int;

  /**
   * Build the schematic
   *
   * @return int
   */
  public function build(): int;

  /**
   * Finalize the build
   *
   * @return int
   */
  public function finalizeBuild(): int;

  /**
   * Prepare to tear down the schematic
   *
   * @return int
   */
  public function prepareTearDown(): int;

  /**
   * Tear down the schematic
   *
   * @return int
   */
  public function tearDown(): int;

  /**
   * Finalize the tear down
   *
   * @return int
   */
  public function finalizeTearDown(): int;
}