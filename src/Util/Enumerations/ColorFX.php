<?php

namespace Assegai\Console\Util\Enumerations;

/**
 * Enumerates the text styles.
 *
 * @package Assegai\Console\Util\Enumerations
 */
enum ColorFX: string
{
  case BLINK = "\033[5m";
  case BOLD = "\033[1m";
  case UNDERLINE = "\033[4m";
  case REVERSE = "\033[7m";
}