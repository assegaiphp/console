<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Assegai\Console\Util\Path;

class PackageSchematicLoader
{
  /**
   * @return SchematicDefinition[]
   */
  public static function load(string $workspace): array
  {
    if (! SchematicWorkspaceConfig::discoverPackages($workspace)) {
      return [];
    }

    $vendorPath = Path::join($workspace, 'vendor');

    if (! is_dir($vendorPath)) {
      return [];
    }

    $definitions = [];
    $packageManifests = glob($vendorPath . '/*/*/composer.json') ?: [];

    foreach ($packageManifests as $composerJsonPath) {
      $decodedManifest = json_decode(file_get_contents($composerJsonPath) ?: '', true);

      if (! is_array($decodedManifest)) {
        continue;
      }

      $schematics = $decodedManifest['extra']['assegai']['schematics'] ?? [];

      if (! is_array($schematics) || $schematics === []) {
        continue;
      }

      $packageRoot = dirname($composerJsonPath);
      $packageName = (string) ($decodedManifest['name'] ?? $packageRoot);

      foreach ($schematics as $relativeManifestPath) {
        if (! is_string($relativeManifestPath) || trim($relativeManifestPath) === '') {
          continue;
        }

        $manifestPath = Path::join($packageRoot, trim($relativeManifestPath, '/'));

        $definitions[] = SchematicManifestLoader::loadFromFile(
          $manifestPath,
          'package',
          $packageName . ':' . $relativeManifestPath,
        );
      }
    }

    return $definitions;
  }
}
