<?php

namespace Assegai\Console\Core\Interfaces;

/**
 * Interface ConfigurableInterface. This interface defines objects that can be configured.
 *
 * @package Assegai\Console\Core\Interfaces
 */
interface ConfigurableInterface
{
  /**
   * This method is used to configure the object.
   *
   * @return void
   */
  public function configure(): void;
}