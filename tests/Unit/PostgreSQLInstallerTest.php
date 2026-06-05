<?php

use Assegai\Console\Installers\PostgreSQLInstaller;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;

function createInstallerWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/assegai-installer-pgsql-' . uniqid('', true);

  if (!mkdir($workspace . '/config', 0777, true) && !is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  copy(__DIR__ . '/../../templates/config/default.php', $workspace . '/config/default.php');

  return $workspace;
}

function deleteInstallerWorkspace(string $directory): void
{
  if (!is_dir($directory)) {
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

describe('PostgreSQL installer', function () {
  it('writes configured databases under the pgsql config key', function () {
    $workspace = createInstallerWorkspace();

    try {
      CliPrompt::fake([
        'text' => ['cinema_hub', '127.0.0.1', 'postgres', '5432'],
        'password' => ['secret'],
      ]);

      $installer = new PostgreSQLInstaller(
        new MockInput(),
        new MockOutput(),
        new FormatterHelper(),
        new QuestionHelper(),
        $workspace
      );

      expect($installer->install())->toBe(Command::SUCCESS);

      $secureConfig = require $workspace . '/config/secure.php';
      $defaultConfig = require $workspace . '/config/default.php';

      expect($secureConfig['databases']['pgsql']['cinema_hub']['host'])->toBe('127.0.0.1');
      expect($secureConfig['databases']['pgsql']['cinema_hub']['user'])->toBe('postgres');
      expect($secureConfig['databases']['pgsql']['cinema_hub']['password'])->toBe('secret');
      expect($secureConfig['databases'])->not->toHaveKey('postgresql');
      expect($defaultConfig['databases'] ?? null)->toBeNull();
    } finally {
      CliPrompt::flushFake();
      deleteInstallerWorkspace($workspace);
    }
  });
});
