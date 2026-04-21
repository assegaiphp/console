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
