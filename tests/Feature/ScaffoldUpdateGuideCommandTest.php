<?php

use Assegai\Console\Commands\Updates\ScaffoldUpdateGuide;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createUpgradeScaffoldWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('update-scaffold-', true);

  if (!mkdir($workspace . '/website/src/Update/Data/upgrades', 0755, true) && !is_dir($workspace . '/website/src/Update/Data/upgrades')) {
    throw new RuntimeException("Failed to create workspace: $workspace");
  }

  if (!mkdir($workspace . '/core/docs/releases', 0755, true) && !is_dir($workspace . '/core/docs/releases')) {
    throw new RuntimeException("Failed to create releases docs directory: $workspace");
  }

  return $workspace;
}

function removeUpgradeScaffoldWorkspace(string $directory): void
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

describe('updates:scaffold command', function () {
  it('creates an update advisor entry and upgrade notes draft', function () {
    $workspace = createUpgradeScaffoldWorkspace();

    try {
      $tester = new CommandTester(new ScaffoldUpdateGuide());
      $status = $tester->execute([
        'from' => '0.9.0',
        'to' => '1.0.0',
        '--directory' => $workspace,
        '--title' => 'Upgrade from 0.9.0 to 1.0.0',
      ]);

      $guideFile = $workspace . '/website/src/Update/Data/upgrades/0.9.0-to-1.0.0.php';
      $notesFile = $workspace . '/core/docs/releases/1.0.0-upgrade-notes-draft.md';

      expect($status)->toBe(Command::SUCCESS)
        ->and(file_exists($guideFile))->toBeTrue()
        ->and(file_exists($notesFile))->toBeTrue()
        ->and(file_get_contents($guideFile) ?: '')->toContain("'from' => '0.9.0'")
        ->and(file_get_contents($guideFile) ?: '')->toContain("'to' => '1.0.0'")
        ->and(file_get_contents($notesFile) ?: '')->toContain('# 1.0.0 Upgrade Notes Draft');
    } finally {
      removeUpgradeScaffoldWorkspace($workspace);
    }
  });
});
