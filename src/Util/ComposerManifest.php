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

  /**
   * Ensure a package meets at least the recommended constraint without lowering newer app constraints.
   *
   * @param array<string, mixed> $composerConfig
   * @return array<string, mixed>
   */
  public static function ensureRecommendedRequirement(
    array $composerConfig,
    string $packageName,
    string $recommendedConstraint,
    string $section = 'require',
  ): array {
    $requirements = $composerConfig[$section] ?? [];

    if (! is_array($requirements)) {
      $requirements = [];
    }

    $existingConstraint = $requirements[$packageName] ?? null;

    if (! is_string($existingConstraint) || $existingConstraint === '') {
      return self::ensureRequirement($composerConfig, $packageName, $recommendedConstraint, $section);
    }

    if (self::constraintVersionCompare($existingConstraint, $recommendedConstraint) >= 0) {
      return $composerConfig;
    }

    return self::ensureRequirement($composerConfig, $packageName, $recommendedConstraint, $section);
  }

  private static function constraintVersionCompare(string $leftConstraint, string $rightConstraint): int
  {
    $leftVersion = self::extractLowestComparableVersion($leftConstraint);
    $rightVersion = self::extractLowestComparableVersion($rightConstraint);

    if ($leftVersion === null || $rightVersion === null) {
      return 1;
    }

    return version_compare($leftVersion, $rightVersion);
  }

  private static function extractLowestComparableVersion(string $constraint): ?string
  {
    if (preg_match_all('/\d+\.\d+\.\d+/', $constraint, $matches) !== 1 || empty($matches[0])) {
      return null;
    }

    $versions = $matches[0];
    usort($versions, static fn(string $left, string $right): int => version_compare($left, $right));

    return $versions[0] ?? null;
  }
}
