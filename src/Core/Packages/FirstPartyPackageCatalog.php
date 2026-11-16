<?php

namespace Assegai\Console\Core\Packages;

use Assegai\Console\Releases\BundledRelease;

class FirstPartyPackageCatalog
{
  private const PACKAGE_VENDOR = 'assegaiphp';

  /** @return array<string, string> Reviewed public-module recommendations for the coordinated release. */
  public static function recommendedConstraints(): array
  {
    return BundledRelease::inventory()->recommendedConstraints();
  }

  /**
   * @return array{packageName: string, constraint: string}|null
   */
  public static function resolve(string $requestedPackage): ?array
  {
    $packageName = self::normalizePackageName($requestedPackage);

    $constraints = self::recommendedConstraints();
    if ($packageName === null || !isset($constraints[$packageName])) {
      return null;
    }

    return [
      'packageName' => $packageName,
      'constraint' => $constraints[$packageName],
    ];
  }

  private static function normalizePackageName(string $requestedPackage): ?string
  {
    $requestedPackage = strtolower(trim($requestedPackage));

    if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $requestedPackage) === 1) {
      return self::PACKAGE_VENDOR . '/' . $requestedPackage;
    }

    if (preg_match('/^' . self::PACKAGE_VENDOR . '\/[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $requestedPackage) === 1) {
      return $requestedPackage;
    }

    return null;
  }
}
