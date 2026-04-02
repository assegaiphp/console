<?php

namespace Assegai\Console\Core\Packages;

class FirstPartyPackageCatalog
{
  /**
   * @return array{packageName: string, constraint: string}|null
   */
  public static function resolve(string $requestedPackage): ?array
  {
    return match (strtolower(trim($requestedPackage))) {
      'orm', PACKAGE_NAME_ORM => [
        'packageName' => PACKAGE_NAME_ORM,
        'constraint' => RECOMMENDED_ORM_VERSION_CONSTRAINT,
      ],
      'events', PACKAGE_NAME_EVENTS => [
        'packageName' => PACKAGE_NAME_EVENTS,
        'constraint' => RECOMMENDED_EVENTS_VERSION_CONSTRAINT,
      ],
      default => null,
    };
  }

  /**
   * @return string[]
   */
  public static function supportedAliases(): array
  {
    return ['orm', 'events'];
  }
}
