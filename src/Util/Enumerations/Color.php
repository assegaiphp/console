<?php

namespace Assegai\Console\Util\Enumerations;

/**
 * Enumerates ANSI color codes.
 *
 * @package Assegai\Console\Util\Enumerations
 */
enum Color: string
{
  case FG_BLACK = "\033[0;30m";
  case FG_DARK_GRAY = "\033[1;30m";
  case FG_BLUE = "\033[0;34m";
  case FG_LIGHT_BLUE = "\033[1;34m";
  case FG_GREEN = "\033[0;32m";
  case FG_LIGHT_GREEN = "\033[1;32m";
  case FG_CYAN = "\033[0;36m";
  case FG_LIGHT_CYAN = "\033[1;36m";
  case FG_RED = "\033[0;31m";
  case FG_LIGHT_RED = "\033[1;31m";
  case FG_PURPLE = "\033[0;35m";
  case FG_LIGHT_PURPLE = "\033[1;35m";
  case FG_BROWN = "\033[0;33m";
  case FG_YELLOW = "\033[1;33m";
  case FG_LIGHT_GRAY = "\033[0;37m";
  case FG_WHITE = "\033[1;37m";
  case BG_BLACK = "\033[40m";
  case BG_RED = "\033[41m";
  case BG_GREEN = "\033[42m";
  case BG_YELLOW = "\033[43m";
  case BG_BLUE = "\033[44m";
  case BG_MAGENTA = "\033[45m";
  case BG_CYAN = "\033[46m";
  case BG_LIGHT_GRAY = "\033[47m";
  case RESET = "\033[0m";
}
