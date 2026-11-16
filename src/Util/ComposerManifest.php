<?php

namespace Assegai\Console\Util;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
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

  public static function resolvePsr4Namespace(string $workspace, string $fallback = 'Assegai\\App'): string
  {
    try {
      $composerConfig = self::load($workspace);
    } catch (RuntimeException) {
      return $fallback;
    }

    $psr4 = $composerConfig['autoload']['psr-4'] ?? [];

    if (! is_array($psr4)) {
      return $fallback;
    }

    foreach ($psr4 as $namespace => $directory) {
      foreach (self::normalizePsr4Directories($directory) as $candidateDirectory) {
        if (self::isSourceDirectory($candidateDirectory)) {
          return rtrim((string) $namespace, '\\');
        }
      }
    }

    return $fallback;
  }

  /**
   * @return string[]
   */
  private static function normalizePsr4Directories(mixed $directories): array
  {
    if (is_string($directories)) {
      return [$directories];
    }

    if (! is_array($directories)) {
      return [];
    }

    return array_values(array_filter($directories, is_string(...)));
  }

  private static function isSourceDirectory(string $directory): bool
  {
    return rtrim(Path::normalize($directory), '/') === 'src';
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
   * Preserve existing support by default; plan upgrades or an actual minimum only with explicit intent.
   *
   * @param array<string, mixed> $composerConfig
   * @return array<string, mixed>
   */
  public static function ensureRecommendedRequirement(
    array $composerConfig,
    string $packageName,
    string $recommendedConstraint,
    string $section = 'require',
    RequirementIntent $intent = RequirementIntent::PreserveSupport,
  ): array {
    $requirements = $composerConfig[$section] ?? [];

    if (! is_array($requirements)) {
      $requirements = [];
    }

    $existingConstraint = $requirements[$packageName] ?? null;

    if ($existingConstraint === null) {
      return self::ensureRequirement($composerConfig, $packageName, $recommendedConstraint, $section);
    }

    if (!is_string($existingConstraint) || trim($existingConstraint) === '') {
      throw new RuntimeException("Please correct the invalid $packageName constraint in $section before updating.");
    }

    try {
      $existing = self::parseRequirement($existingConstraint, bounded: false);
      $recommended = self::parseRequirement($recommendedConstraint, bounded: false);
      if ($intent === RequirementIntent::PreserveSupport) { return $composerConfig; }
      if (Intervals::isSubsetOf($existing, $recommended)
        || Intervals::isSubsetOf($existing, new Constraint('>=', $recommended->getUpperBound()->getVersion()))) {
        return $composerConfig;
      }
      if (Intervals::isSubsetOf($recommended, $existing)) {
        if ($intent === RequirementIntent::EnforceMinimum && $existing->getLowerBound()->compareTo($recommended->getLowerBound(), '<')) {
          return self::ensureRequirement($composerConfig, $packageName, $recommendedConstraint, $section);
        }
        return $composerConfig;
      }
      if (Intervals::isSubsetOf($existing, new Constraint('<', $recommended->getLowerBound()->getVersion()))) {
        return self::ensureRequirement($composerConfig, $packageName, $recommendedConstraint, $section);
      }
    } catch (\UnexpectedValueException | \InvalidArgumentException $error) {
      throw new RuntimeException("Please correct the $packageName constraint before updating: " . $error->getMessage(), previous: $error);
    }
    throw new RuntimeException("Please review the custom $packageName constraint '$existingConstraint' against '$recommendedConstraint'. The update cannot change it without broadening or downgrading it.");
  }

  /** Parse numeric support; qualification additionally requires finite bounds. */
  public static function parseRequirement(string $constraint, bool $bounded = true): ConstraintInterface
  {
    if ($constraint === '' || strlen($constraint) > 1024 || preg_match('/@(?!stable\b)|-(?:dev|alpha|beta|rc)/i', $constraint)) {
      throw new \InvalidArgumentException('Please review the unsupported requirement or development stability flag.');
    }
    $range = (new VersionParser())->parseConstraints($constraint);
    $intervals = Intervals::get($range);
    if ($intervals['numeric'] === [] || $intervals['branches']['names'] !== [] || $intervals['branches']['exclude']
      || ($bounded && $range->getUpperBound()->isPositiveInfinity())) {
      throw new \InvalidArgumentException('Please review the unsupported development, empty or unbounded requirement.');
    }
    return $range;
  }

  public static function removesSupport(string $before, string $after): bool
  {
    return !Intervals::isSubsetOf(self::parseRequirement($before, bounded: false), self::parseRequirement($after, bounded: false));
  }
}
