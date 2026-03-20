<?php

namespace Assegai\Console\Core\Schematics\Registry;

use RuntimeException;
use Assegai\Console\Util\Path;

class LocalSchematicLoader
{
  /**
   * @return SchematicDefinition[]
   */
  public static function load(string $workspace): array
  {
    if (SchematicWorkspaceConfig::allowOverrides($workspace)) {
      throw new RuntimeException('Custom schematic overrides are not supported in v1.');
    }

    $definitions = [];

    foreach (SchematicWorkspaceConfig::localPaths($workspace) as $path) {
      $root = Path::join($workspace, $path);

      if (! is_dir($root)) {
        continue;
      }

      foreach (self::discoverManifestFiles($root) as $manifestPath) {
        $definitions[] = SchematicManifestLoader::loadFromFile(
          $manifestPath,
          'local',
          $manifestPath,
        );
      }
    }

    return $definitions;
  }

  /**
   * @return string[]
   */
  private static function discoverManifestFiles(string $root): array
  {
    $files = [];
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if (! $file->isFile()) {
        continue;
      }

      if ($file->getFilename() !== 'schematic.json') {
        continue;
      }

      $files[] = Path::normalize($file->getPathname());
    }

    sort($files);

    return $files;
  }
}
