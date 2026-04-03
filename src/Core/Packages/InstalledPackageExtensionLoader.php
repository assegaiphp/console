<?php

namespace Assegai\Console\Core\Packages;

use Assegai\Console\Util\Path;

class InstalledPackageExtensionLoader
{
  /**
   * @return InstalledPackageExtension[]
   */
  public static function load(string $workspace, bool $requireAutoload = true): array
  {
    $vendorPath = Path::join($workspace, 'vendor');

    if (!is_dir($vendorPath)) {
      return [];
    }

    if ($requireAutoload) {
      $workspaceAutoload = Path::join($workspace, 'vendor', 'autoload.php');

      if (is_file($workspaceAutoload)) {
        require_once $workspaceAutoload;
      }
    }

    $extensions = [];
    $packageManifests = glob($vendorPath . '/*/*/composer.json') ?: [];

    foreach ($packageManifests as $composerJsonPath) {
      $decodedManifest = json_decode(file_get_contents($composerJsonPath) ?: '', true);

      if (!is_array($decodedManifest)) {
        continue;
      }

      $extension = self::fromComposerManifest($decodedManifest, dirname($composerJsonPath));

      if ($extension === null) {
        continue;
      }

      $extensions[] = $extension;
    }

    return $extensions;
  }

  public static function find(string $workspace, string $packageName, bool $requireAutoload = true): ?InstalledPackageExtension
  {
    foreach (self::load($workspace, $requireAutoload) as $extension) {
      if ($extension->packageName === $packageName) {
        return $extension;
      }
    }

    return null;
  }

  public static function resolve(string $workspace, string $nameOrAlias, bool $requireAutoload = true): ?InstalledPackageExtension
  {
    foreach (self::load($workspace, $requireAutoload) as $extension) {
      if ($extension->matches($nameOrAlias)) {
        return $extension;
      }
    }

    return null;
  }

  /**
   * @param array<string, mixed> $manifest
   */
  private static function fromComposerManifest(array $manifest, string $packageRoot): ?InstalledPackageExtension
  {
    $packageName = trim((string) ($manifest['name'] ?? ''));

    if ($packageName === '') {
      return null;
    }

    $assegai = $manifest['extra']['assegai'] ?? null;

    if (!is_array($assegai)) {
      return null;
    }

    $aliases = array_values(array_filter(
      array_map(static fn(mixed $alias): string => is_string($alias) ? trim($alias) : '', (array) ($assegai['aliases'] ?? [])),
      static fn(string $alias): bool => $alias !== '',
    ));

    $commandClasses = array_values(array_filter(
      array_map(static fn(mixed $commandClass): string => is_string($commandClass) ? trim($commandClass) : '', (array) ($assegai['commands'] ?? [])),
      static fn(string $commandClass): bool => $commandClass !== '',
    ));

    $installerClass = isset($assegai['installer']) && is_string($assegai['installer']) && trim($assegai['installer']) !== ''
      ? trim($assegai['installer'])
      : null;

    if ($aliases === [] && $commandClasses === [] && $installerClass === null) {
      return null;
    }

    return new InstalledPackageExtension(
      packageName: $packageName,
      packageRoot: $packageRoot,
      aliases: $aliases,
      commandClasses: $commandClasses,
      installerClass: $installerClass,
    );
  }
}
