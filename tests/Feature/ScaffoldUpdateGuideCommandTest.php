<?php

use Assegai\Console\Commands\Updates\ScaffoldUpdateGuide;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createUpgradeScaffoldWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('update-scaffold-', true);

  if (!mkdir($workspace, 0755, true)) {
    throw new RuntimeException("Failed to create workspace: $workspace");
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
        '--output' => $workspace,
        '--title' => 'Upgrade from 0.9.0 to 1.0.0',
      ]);

      $guideFile = $workspace . '/0.9.0-to-1.0.0.php';
      $notesFile = $workspace . '/1.0.0-upgrade-notes-draft.md';

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

  it('removes guessed repository writes and refuses missing, live or existing draft targets', function () {
    $workspace = createUpgradeScaffoldWorkspace();
    try {
      $tester = new CommandTester(new ScaffoldUpdateGuide());
      $arguments = ['from' => '0.10.0', 'to' => '0.11.1'];
      expect($tester->execute($arguments))->toBe(Command::FAILURE);
      file_put_contents($workspace . '/composer.json', '{"name":"assegaiphp/website"}');
      mkdir($workspace . '/content');
      expect($tester->execute($arguments + ['--output' => $workspace . '/content']))->toBe(Command::FAILURE)
        ->and(scandir($workspace . '/content'))->toBe(['.', '..']);
      unlink($workspace . '/composer.json');
      file_put_contents($workspace . '/0.11.1-upgrade-notes-draft.md', 'preserve');
      expect($tester->execute($arguments + ['--output' => $workspace]))->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/0.11.1-upgrade-notes-draft.md'))->toBe('preserve')
        ->and(file_exists($workspace . '/0.10.0-to-0.11.1.php'))->toBeFalse()
        ->and(file_exists($workspace . '/website'))->toBeFalse()
        ->and(file_exists($workspace . '/core'))->toBeFalse();
    } finally {
      removeUpgradeScaffoldWorkspace($workspace);
    }
  });
});
