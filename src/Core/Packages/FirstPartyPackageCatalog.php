<?php

namespace Assegai\Console\Core\Packages;

class FirstPartyPackageCatalog
{
  private const PACKAGE_VENDOR = 'assegaiphp';

  /**
   * @return array{packageName: string, constraint: string}|null
   */
  public static function resolve(string $requestedPackage): ?array
  {
    $packageName = self::normalizePackageName($requestedPackage);

    if ($packageName === null) {
      return null;
    }

    return match ($packageName) {
      PACKAGE_NAME_CORE => [
        'packageName' => PACKAGE_NAME_CORE,
        'constraint' => RECOMMENDED_CORE_VERSION_CONSTRAINT,
      ],
      PACKAGE_NAME_ORM => [
        'packageName' => PACKAGE_NAME_ORM,
        'constraint' => RECOMMENDED_ORM_VERSION_CONSTRAINT,
      ],
      PACKAGE_NAME_EVENTS => [
        'packageName' => PACKAGE_NAME_EVENTS,
        'constraint' => RECOMMENDED_EVENTS_VERSION_CONSTRAINT,
      ],
      default => [
        'packageName' => $packageName,
        'constraint' => '*',
      ],
    };
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
