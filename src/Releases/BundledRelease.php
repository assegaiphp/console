<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

use RuntimeException;

/** Offline distribution of Core-owned data, copied during release preparation. */
final class BundledRelease
{
  private static ?ReleaseInventory $inventory = null;

  public static function inventory(): ReleaseInventory
  {
    return self::$inventory ??= self::load(__DIR__ . '/../../meta/release');
  }

  public static function load(string $directory): ReleaseInventory
  {
    $reader = new ReleaseInventoryValidator();
    [$source] = $reader->read($directory . '/source.json');
    ReleaseInventoryValidator::keys($source, ['schema', 'repository', 'sourceSha', 'canonicalModulesPath', 'inventoryPath', 'canonicalModulesSha256', 'inventorySha256']);
    if ($source['schema'] !== 1 || $source['repository'] !== 'assegaiphp/core'
      || !is_string($source['sourceSha']) || preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/', $source['sourceSha']) !== 1
      || !is_string($source['canonicalModulesPath']) || !is_string($source['inventoryPath'])
      || !ReleaseInventoryValidator::isHash($source['canonicalModulesSha256']) || !ReleaseInventoryValidator::isHash($source['inventorySha256'])) {
      throw new RuntimeException('Bundled release data requires a recorded Core source and exact input hashes.');
    }
    return $reader->loadInventory($directory . '/inventory.json', $directory . '/modules.json', $source['inventorySha256'], $source['canonicalModulesSha256']);
  }
}
