<?php

namespace Assegai\Console\Core;

use Assegai\Console\Util\Path;

/**
 * Loads the default project templates used for new workspaces and upgrades.
 */
class ProjectTemplateDefaults
{
  /**
   * @return array<string, mixed>
   */
  public static function loadAssegaiConfig(): array
  {
    return self::loadJsonTemplate('assegai.json', self::defaultAssegaiConfig());
  }

  /**
   * @return array<string, mixed>
   */
  public static function loadComposerConfig(): array
  {
    return self::loadJsonTemplate('composer.json', self::defaultComposerConfig());
  }

  /**
   * @param array<string, mixed> $config
   * @return array<string, mixed>
   */
  public static function hydrateAssegaiConfig(array $config = []): array
  {
    return self::mergeDefaults(self::loadAssegaiConfig(), $config);
  }

  /**
   * @param array<string, mixed> $config
   * @return array<string, mixed>
   */
  public static function hydrateComposerConfig(array $config = []): array
  {
    return self::mergeDefaults(self::loadComposerConfig(), $config);
  }

  /**
   * Merge nested associative defaults without overwriting user-provided values.
   *
   * @param array<string, mixed> $defaults
   * @param array<string, mixed> $config
   * @return array<string, mixed>
   */
  public static function mergeDefaults(array $defaults, array $config): array
  {
    $merged = $config;

    foreach ($defaults as $key => $value) {
      if (! array_key_exists($key, $merged)) {
        $merged[$key] = $value;
        continue;
      }

      if (
        is_array($value) &&
        is_array($merged[$key]) &&
        ! array_is_list($value) &&
        ! array_is_list($merged[$key])
      ) {
        $merged[$key] = self::mergeDefaults($value, $merged[$key]);
      }
    }

    return $merged;
  }

  /**
   * @param array<string, mixed> $fallback
   * @return array<string, mixed>
   */
  private static function loadJsonTemplate(string $filename, array $fallback): array
  {
    $templatePath = Path::join(Path::getTemplatesDirectory(), $filename);

    if (! file_exists($templatePath)) {
      return $fallback;
    }

    $decoded = json_decode(file_get_contents($templatePath) ?: '', true);

    if (! is_array($decoded)) {
      return $fallback;
    }

    return self::mergeDefaults($fallback, $decoded);
  }

  /**
   * @return array<string, mixed>
   */
  private static function defaultAssegaiConfig(): array
  {
    return [
      'name' => '',
      'description' => '',
      'version' => DEFAULT_PROJECT_VERSION,
      'projectType' => DEFAULT_PROJECT_TYPE,
      'root' => '',
      'sourceRoot' => 'src',
      'scripts' => [
        'test' => 'vendor/bin/pest tests',
      ],
      'development' => [
        'server' => [
          'runtime' => 'php',
          'host' => DEFAULT_DEV_SERVER_HOST,
          'port' => DEFAULT_DEV_SERVER_PORT,
          'openBrowser' => false,
          'openswoole' => [
            'workerNum' => 1,
            'taskWorkerNum' => 0,
            'maxRequest' => 0,
            'enableCoroutine' => true,
            'hookFlags' => 'all',
          ],
        ],
      ],
      'cli' => [
        'schematics' => [
          'paths' => ['schematics'],
          'discoverPackages' => true,
          'allowOverrides' => false,
        ],
      ],
      'apiDocs' => [
        'enabled' => true,
        'exportOnServe' => false,
        'exportPath' => 'generated/openapi.json',
      ],
      'events' => [
        'wildcards' => true,
        'delimiter' => '.',
        'maxListeners' => null,
        'outbox' => [
          'queue' => 'rabbitmq.events',
          'batchSize' => 100,
          'retryDelaySeconds' => 60,
        ],
      ],
      'webComponents' => [
        'enabled' => true,
        'prefix' => 'app',
        'bundleUrl' => null,
        'bundlePath' => null,
        'output' => 'public/js/assegai-components.min.js',
        'buildOnDumpAutoload' => false,
        'hotReload' => [
          'enabled' => true,
          'path' => 'public/.assegai/wc-hot-reload.json',
          'pollInterval' => 1000,
          'ttl' => 43200,
        ],
      ],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private static function defaultComposerConfig(): array
  {
    return [
      'name' => 'assegaiphp/app',
      'description' => '',
      'type' => DEFAULT_PROJECT_TYPE,
      'scripts' => [
        'start' => 'php -S localhost:5000 bootstrap.php',
        'test' => 'vendor/bin/pest',
      ],
      'license' => 'MIT',
      'autoload' => [
        'psr-4' => [],
      ],
      'authors' => [],
      'require' => [
        'php' => '^' . MIN_PHP_VERSION,
        'ext-pdo' => '*',
        'ext-curl' => '*',
        'vlucas/phpdotenv' => '^5.4',
        PACKAGE_NAME_CORE => RECOMMENDED_CORE_VERSION_CONSTRAINT,
      ],
    ];
  }
}
