<?php

namespace Assegai\Console\WebComponents;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;

final class WebComponentConfig
{
  public const string DEFAULT_PREFIX = 'app';
  public const string DEFAULT_OUTPUT = 'public/js/assegai-components.min.js';
  public const string DEFAULT_HOT_RELOAD_PATH = 'public/.assegai/wc-hot-reload.json';
  public const int DEFAULT_HOT_RELOAD_INTERVAL = 1000;
  public const int DEFAULT_HOT_RELOAD_TTL = 43200;
  public const string CACHE_DIRECTORY = '.cache/assegai';
  public const string ENTRY_FILENAME = 'wc-entry.ts';
  public const string MANIFEST_FILENAME = 'web-components.manifest.json';

  private final function __construct()
  {
  }

  /**
   * @return array<string, mixed>
   */
  public static function load(string $workspace): array
  {
    $configFilename = Path::join($workspace, 'assegai.json');

    if (!is_file($configFilename)) {
      return [];
    }

    $contents = file_get_contents($configFilename);

    if (!$contents) {
      return [];
    }

    $config = json_decode($contents, true);

    return is_array($config) ? $config : [];
  }

  public static function ensureDefaults(string $workspace): void
  {
    $configFilename = Path::join($workspace, 'assegai.json');

    if (!is_file($configFilename)) {
      return;
    }

    $config = self::load($workspace);
    $webComponents = is_array($config['webComponents'] ?? null)
      ? $config['webComponents']
      : [];

    $defaults = [
      'prefix' => self::DEFAULT_PREFIX,
      'output' => self::DEFAULT_OUTPUT,
      'buildOnDumpAutoload' => false,
      'hotReload' => [
        'enabled' => true,
        'path' => self::DEFAULT_HOT_RELOAD_PATH,
        'pollInterval' => self::DEFAULT_HOT_RELOAD_INTERVAL,
        'ttl' => self::DEFAULT_HOT_RELOAD_TTL,
      ],
    ];

    $config['webComponents'] = [...$defaults, ...$webComponents];

    if (is_array($defaults['hotReload']) && is_array($webComponents['hotReload'] ?? null)) {
      $config['webComponents']['hotReload'] = [...$defaults['hotReload'], ...$webComponents['hotReload']];
    }

    file_put_contents($configFilename, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  public static function getPrefix(string $workspace): string
  {
    $config = self::load($workspace);
    $prefix = $config['webComponents']['prefix'] ?? self::DEFAULT_PREFIX;

    if (!is_string($prefix) || trim($prefix) === '') {
      return self::DEFAULT_PREFIX;
    }

    return (new Text($prefix))->kebabCase();
  }

  public static function getOutputPath(string $workspace): string
  {
    $config = self::load($workspace);
    $output = $config['webComponents']['output'] ?? self::DEFAULT_OUTPUT;

    if (!is_string($output) || trim($output) === '') {
      return self::DEFAULT_OUTPUT;
    }

    return ltrim(Path::normalize($output), '/');
  }

  public static function shouldBuildOnDumpAutoload(string $workspace): bool
  {
    $config = self::load($workspace);

    return (bool)($config['webComponents']['buildOnDumpAutoload'] ?? false);
  }

  public static function isHotReloadEnabled(string $workspace): bool
  {
    $config = self::load($workspace);

    return (bool)($config['webComponents']['hotReload']['enabled'] ?? true);
  }

  public static function getHotReloadPath(string $workspace): string
  {
    $config = self::load($workspace);
    $path = $config['webComponents']['hotReload']['path'] ?? self::DEFAULT_HOT_RELOAD_PATH;

    if (!is_string($path) || trim($path) === '') {
      return self::DEFAULT_HOT_RELOAD_PATH;
    }

    return ltrim(Path::normalize($path), '/');
  }

  public static function getHotReloadBrowserPath(string $workspace): string
  {
    $path = '/' . ltrim(self::getHotReloadPath($workspace), '/');

    if (str_starts_with($path, '/public/')) {
      return substr($path, strlen('/public'));
    }

    return $path;
  }

  public static function getHotReloadPollInterval(string $workspace): int
  {
    $config = self::load($workspace);
    $interval = (int)($config['webComponents']['hotReload']['pollInterval'] ?? self::DEFAULT_HOT_RELOAD_INTERVAL);

    return $interval > 0 ? $interval : self::DEFAULT_HOT_RELOAD_INTERVAL;
  }

  public static function getHotReloadTtl(string $workspace): int
  {
    $config = self::load($workspace);
    $ttl = (int)($config['webComponents']['hotReload']['ttl'] ?? self::DEFAULT_HOT_RELOAD_TTL);

    return $ttl > 0 ? $ttl : self::DEFAULT_HOT_RELOAD_TTL;
  }

  public static function makeSelector(string $workspace, string $name): string
  {
    $prefix = self::getPrefix($workspace);
    $baseName = basename(str_replace('\\', '/', $name));

    return $prefix . '-' . (new Text($baseName))->kebabCase();
  }

  public static function getCacheDirectory(string $workspace): string
  {
    return Path::join($workspace, self::CACHE_DIRECTORY);
  }

  public static function getEntryFilename(string $workspace): string
  {
    return Path::join(self::getCacheDirectory($workspace), self::ENTRY_FILENAME);
  }

  public static function getManifestFilename(string $workspace): string
  {
    return Path::join(self::getCacheDirectory($workspace), self::MANIFEST_FILENAME);
  }
}
