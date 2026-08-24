<?php

namespace Assegai\Console\Util;

final class CliInputNormalizer
{
  /**
   * Normalize the two human-friendly global update forms into a Symfony command name.
   *
   * Only the command prefix is inspected so command-specific flags such as
   * `assegai new app -g` keep their existing meaning.
   *
   * @param string[] $argv
   * @return string[]
   */
  public static function normalize(array $argv): array
  {
    if (
      isset($argv[1], $argv[2]) &&
      in_array($argv[1], ['global', '-g', '--global'], true) &&
      $argv[2] === 'update'
    ) {
      array_splice($argv, 1, 2, ['global:update']);
    }

    return array_values($argv);
  }
}
