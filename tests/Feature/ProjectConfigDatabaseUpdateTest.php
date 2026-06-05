<?php

use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\ProjectConfig;

if (! function_exists('env')) {
  function env(string $key, mixed $default = null): mixed
  {
    return $default;
  }
}

function createProjectConfigWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('project-config-update-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  copy(__DIR__ . '/../../templates/config/default.php', $workspace . '/config/default.php');
  copy(__DIR__ . '/../../templates/config/secure.php', $workspace . '/config/secure.php');

  return $workspace;
}

/**
 * @param string|string[] $directories
 */
function writeProjectConfigComposer(string $workspace, string $namespace = 'Acme\\ClonedApp\\', string|array $directories = 'src/'): void
{
  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        $namespace => $directories,
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}


function deleteProjectConfigWorkspace(string $directory): void
{
  if (! is_dir($directory)) {
    return;
  }

  $items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($items as $item) {
    if ($item->isDir()) {
      rmdir($item->getPathname());
      continue;
    }

    unlink($item->getPathname());
  }

  rmdir($directory);
}

describe('ProjectConfig database updates', function () {
  it('can update the secure database config without flattening auth expressions', function () {
    $workspace = createProjectConfigWorkspace();

    try {
      $projectConfig = new ProjectConfig(new MockInput(), new MockOutput());

      $bytes = $projectConfig->updateDatabaseConfig([
        'databases' => [
          'mysql' => [
            'users' => [
              'host' => '127.0.0.1',
              'user' => 'root',
              'password' => '',
              'port' => 3306,
            ],
          ],
        ],
      ], $workspace);

      expect($bytes)->not->toBeFalse();

      $config = require $workspace . '/config/secure.php';
      $contents = file_get_contents($workspace . '/config/secure.php') ?: '';

      expect($config['databases']['mysql']['users']['host'])->toBe('127.0.0.1');
      expect($contents)->toContain("env('APP_SECRET_KEY', 'your-secret-key')");
      expect($contents)->toContain('Assegai\\App\\Users\\Entities\\UserEntity::class');
    } finally {
      deleteProjectConfigWorkspace($workspace);
    }
  });

  it('creates secure config for database updates instead of writing to shared defaults', function () {
    $workspace = createProjectConfigWorkspace();

    try {
      $secureConfigPath = $workspace . '/config/secure.php';
      $defaultConfigPath = $workspace . '/config/default.php';
      unlink($secureConfigPath);
      $defaultConfigBefore = file_get_contents($defaultConfigPath) ?: '';
      writeProjectConfigComposer($workspace);
      $projectConfig = new ProjectConfig(new MockInput(), new MockOutput());

      $bytes = $projectConfig->updateDatabaseConfig([
        'databases' => [
          'mysql' => [
            'cloned_app' => [
              'host' => '127.0.0.1',
              'user' => 'root',
              'password' => 'secret',
              'port' => 3306,
            ],
          ],
        ],
      ], $workspace);

      expect($bytes)->not->toBeFalse();
      expect(file_exists($secureConfigPath))->toBeTrue();
      expect(file_get_contents($defaultConfigPath))->toBe($defaultConfigBefore);

      $secureConfig = require $secureConfigPath;
      $defaultConfig = require $defaultConfigPath;

      expect($secureConfig['databases']['mysql']['cloned_app']['password'])->toBe('secret');
      expect($secureConfig['authentication']['jwt']['entityClassName'])
        ->toBe('Acme\\ClonedApp\\Users\\Entities\\UserEntity');
      expect($defaultConfig['databases'] ?? null)->toBeNull();
    } finally {
      deleteProjectConfigWorkspace($workspace);
    }
  });

  it('preserves databases from default config when recreating secure config', function () {
    $workspace = createProjectConfigWorkspace();

    try {
      $secureConfigPath = $workspace . '/config/secure.php';
      $defaultConfigPath = $workspace . '/config/default.php';
      unlink($secureConfigPath);
      writeProjectConfigComposer($workspace);

      $defaultConfigContents = file_get_contents($defaultConfigPath) ?: '';
      $defaultConfigContents = upsert_php_array_config_section($defaultConfigContents, 'databases', [
        'sqlite' => [
          'existing_blog' => [
            'path' => '.data/existing_blog.sq3',
          ],
        ],
        'mysql' => [
          'legacy_app' => [
            'host' => '127.0.0.1',
            'user' => 'root',
            'password' => 'legacy-secret',
            'port' => 3306,
          ],
        ],
      ]);

      if ($defaultConfigContents === false) {
        throw new RuntimeException('Failed to update default config fixture.');
      }

      file_put_contents($defaultConfigPath, $defaultConfigContents);

      $projectConfig = new ProjectConfig(new MockInput(), new MockOutput());
      $bytes = $projectConfig->updateDatabaseConfig([
        'databases' => [
          'sqlite' => [
            'new_blog' => [
              'path' => '.data/new_blog.sq3',
            ],
          ],
        ],
      ], $workspace);

      expect($bytes)->not->toBeFalse();

      $secureConfig = require $secureConfigPath;

      expect($secureConfig['databases']['sqlite']['existing_blog']['path'])->toBe('.data/existing_blog.sq3');
      expect($secureConfig['databases']['mysql']['legacy_app']['password'])->toBe('legacy-secret');
      expect($secureConfig['databases']['sqlite']['new_blog']['path'])->toBe('.data/new_blog.sq3');
      expect($secureConfig['authentication']['jwt']['entityClassName'])
        ->toBe('Acme\\ClonedApp\\Users\\Entities\\UserEntity');
    } finally {
      deleteProjectConfigWorkspace($workspace);
    }
  });

  it('rewrites recreated secure config namespaces from composer PSR-4 directory arrays', function () {
    $workspace = createProjectConfigWorkspace();

    try {
      $secureConfigPath = $workspace . '/config/secure.php';
      unlink($secureConfigPath);
      writeProjectConfigComposer($workspace, 'Acme\\ArrayApp\\', ['generated/', 'src/']);
      $projectConfig = new ProjectConfig(new MockInput(), new MockOutput());

      $bytes = $projectConfig->updateDatabaseConfig([
        'databases' => [
          'sqlite' => [
            'array_app' => [
              'path' => '.data/array_app.sq3',
            ],
          ],
        ],
      ], $workspace);

      expect($bytes)->not->toBeFalse();

      $secureConfig = require $secureConfigPath;

      expect($secureConfig['authentication']['jwt']['entityClassName'])
        ->toBe('Acme\\ArrayApp\\Users\\Entities\\UserEntity');
    } finally {
      deleteProjectConfigWorkspace($workspace);
    }
  });

  it('writes sqlite paths without escaping forward slashes', function () {
    $workspace = createProjectConfigWorkspace();

    try {
      $projectConfig = new ProjectConfig(new MockInput(), new MockOutput());

      $bytes = $projectConfig->updateDatabaseConfig([
        'databases' => [
          'sqlite' => [
            'users' => [
              'path' => '.data/users.sq3',
            ],
          ],
        ],
      ], $workspace);

      expect($bytes)->not->toBeFalse();

      $contents = file_get_contents($workspace . '/config/secure.php');
      expect($contents)->toContain('.data/users.sq3');
      expect($contents)->not->toContain('.data\\/users.sq3');
      expect($contents)->toContain("env('APP_SECRET_KEY', 'your-secret-key')");
    } finally {
      deleteProjectConfigWorkspace($workspace);
    }
  });
});
