<?php

namespace Assegai\Console\Core\Packages;

class FirstPartyPackageCatalog
{
  private const PACKAGE_VENDOR = 'assegaiphp';

  /** @return array<string, string> Published package lines compatible with the supported Core baseline. */
  public static function recommendedConstraints(): array
  {
    return [
      PACKAGE_NAME_CLI => RECOMMENDED_CLI_VERSION_CONSTRAINT,
      PACKAGE_NAME_CORE => RECOMMENDED_CORE_VERSION_CONSTRAINT,
      PACKAGE_NAME_ORM => RECOMMENDED_ORM_VERSION_CONSTRAINT,
      PACKAGE_NAME_EVENTS => RECOMMENDED_EVENTS_VERSION_CONSTRAINT,
      'assegaiphp/auth' => '^0.10.0',
      'assegaiphp/collections' => '^0.10.0',
      'assegaiphp/common' => '^0.10.1',
      'assegaiphp/forms' => '^0.10.0',
      'assegaiphp/util' => '^0.10.0',
      'assegaiphp/validation' => '^0.10.0',
    ];
  }

  /**
   * @return array{packageName: string, constraint: string}|null
   */
  public static function resolve(string $requestedPackage): ?array
  {
    $packageName = self::normalizePackageName($requestedPackage);

    if ($packageName === null) {
      return null;
    }

    return [
      'packageName' => $packageName,
      'constraint' => self::recommendedConstraints()[$packageName] ?? '*',
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
