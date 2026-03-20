<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Assegai\Console\Core\ProjectTemplateDefaults;
use Assegai\Console\Util\Path;

class SchematicWorkspaceConfig
{
  /**
   * @return array<string, mixed>
   */
  public static function load(string $workspace): array
  {
    $configPath = Path::join($workspace, 'assegai.json');

    if (! is_file($configPath)) {
      return ProjectTemplateDefaults::hydrateAssegaiConfig();
    }

    $decoded = json_decode(file_get_contents($configPath) ?: '', true);

    if (! is_array($decoded)) {
      return ProjectTemplateDefaults::hydrateAssegaiConfig();
    }

    return ProjectTemplateDefaults::hydrateAssegaiConfig($decoded);
  }

  /**
   * @return string[]
   */
  public static function localPaths(string $workspace): array
  {
    $schematicsConfig = self::load($workspace)['cli']['schematics'] ?? [];
    $paths = $schematicsConfig['paths'] ?? ['schematics'];

    if (! is_array($paths)) {
      return ['schematics'];
    }

    return array_values(array_filter(
      array_map(static fn(mixed $path): string => is_string($path) ? trim($path, '/') : '', $paths),
      static fn(string $path): bool => $path !== ''
    ));
  }

  public static function discoverPackages(string $workspace): bool
  {
    return (bool) ((self::load($workspace)['cli']['schematics']['discoverPackages'] ?? true));
  }

  public static function allowOverrides(string $workspace): bool
  {
    return (bool) ((self::load($workspace)['cli']['schematics']['allowOverrides'] ?? false));
  }
}
