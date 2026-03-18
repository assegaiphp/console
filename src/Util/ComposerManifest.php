<?php

namespace Assegai\Console\Util;

use RuntimeException;

/**
 * Reads and writes a workspace composer.json manifest.
 */
class ComposerManifest
{
  /**
   * @return array<string, mixed>
   */
  public static function load(string $workspace): array
  {
    $filename = Path::join($workspace, 'composer.json');

    if (! file_exists($filename)) {
      throw new RuntimeException("composer.json not found in workspace: $workspace");
    }

    $decoded = json_decode(file_get_contents($filename) ?: '', true);

    if (! is_array($decoded)) {
      throw new RuntimeException("Failed to decode composer.json in workspace: $workspace");
    }

    return $decoded;
  }

  /**
   * @param array<string, mixed> $composerConfig
   */
  public static function save(string $workspace, array $composerConfig): bool
  {
    $filename = Path::join($workspace, 'composer.json');

    return false !== file_put_contents(
      $filename,
      json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );
  }

  /**
   * @param array<string, mixed> $composerConfig
   * @return array<string, mixed>
   */
  public static function ensureRequirement(
    array $composerConfig,
    string $packageName,
    string $constraint,
    string $section = 'require',
  ): array
  {
    $requirements = $composerConfig[$section] ?? [];

    if (! is_array($requirements)) {
      $requirements = [];
    }

    $requirements[$packageName] = $constraint;
    ksort($requirements);
    $composerConfig[$section] = $requirements;

    return $composerConfig;
  }
}
