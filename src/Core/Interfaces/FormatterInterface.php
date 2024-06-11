<?php

namespace Assegai\Console\Core\Interfaces;

/**
 * Interface FormatterInterface. This interface defines the methods that a formatter must implement.
 *
 * @package Assegai\Console\Core\Interfaces
 */
interface FormatterInterface
{
  /**
   * Formats the input string.
   *
   * @param string $input The input string
   *
   * @return string The formatted string
   */
  public function getFormatted(string $input): string;
}