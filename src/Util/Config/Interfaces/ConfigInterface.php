<?php

namespace Assegai\Console\Util\Config\Interfaces;

interface ConfigInterface
{
  /**
   * Load the config source
   *
   * @return int
   */
  public function load(): int;

  /**
   * Get a value from the config file
   *
   * @param string $path The path to the value
   * @return mixed
   */
  public function get(string $path): mixed;

  /**
   * Set a value in the config file
   *
   * @param string $path The path to the value
   * @param mixed $value The value to set
   * @return void
   */
  public function set(string $path, mixed $value): void;

  /**
   * Save the config file
   *
   * @return int
   */
  public function commit(): int;
}