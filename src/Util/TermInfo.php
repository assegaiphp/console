<?php

namespace Assegai\Console\Util;

class TermInfo
{
  /**
   * Returns the size of the terminal window.
   *
   * @return array{width: int, height: int} The width and height of the terminal window.
   */
  public static function windowSize(): array
  {
    return [
      'width' => intval(exec('tput cols')),
      'height' => intval(exec('tput lines'))
    ];
  }
}