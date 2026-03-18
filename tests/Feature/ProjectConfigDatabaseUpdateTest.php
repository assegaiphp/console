<?php

use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\ProjectConfig;

function createProjectConfigWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('project-config-update-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  copy(__DIR__ . '/../../templates/config/default.php', $workspace . '/config/default.php');

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
  it('can update the template database config even when it uses env()', function () {
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

      $config = require $workspace . '/config/default.php';

      expect($config['databases']['mysql']['users']['host'])->toBe('127.0.0.1');
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

      $contents = file_get_contents($workspace . '/config/default.php');
      expect($contents)->toContain('.data/users.sq3');
      expect($contents)->not->toContain('.data\\/users.sq3');
    } finally {
      deleteProjectConfigWorkspace($workspace);
    }
  });
});
