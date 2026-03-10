<?php

use Assegai\Console\Installers\AbstractInstaller;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;

function remove_directory(string $path): void
{
  if (! is_dir($path)) {
    return;
  }

  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
    \RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($iterator as $item) {
    if ($item->isDir()) {
      rmdir($item->getPathname());
      continue;
    }

    unlink($item->getPathname());
  }

  rmdir($path);
}

describe('Installer defaults', function () {
  it('derives the suggested database name from the configured project name', function () {
    $projectPath = sys_get_temp_dir() . '/assegai-installer-' . uniqid();
    mkdir($projectPath, 0777, true);
    file_put_contents($projectPath . '/assegai.json', json_encode([
      'name' => 'Payroll Console',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $installer = new class(new MockInput(), new MockOutput(), new FormatterHelper(), new QuestionHelper(), $projectPath) extends AbstractInstaller {
      public function install(): int
      {
        return Command::SUCCESS;
      }

      public function suggestedDatabaseName(): string
      {
        return $this->getSuggestedDatabaseName();
      }

      public function suggestedSQLitePath(): string
      {
        return $this->getSuggestedSQLitePath();
      }
    };

    expect($installer->suggestedDatabaseName())->toBe('payroll_console');
    expect($installer->suggestedSQLitePath())->toBe('.data/payroll_console.sq3');

    remove_directory($projectPath);
  });
});
