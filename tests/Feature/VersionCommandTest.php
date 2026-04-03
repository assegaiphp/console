<?php

use Assegai\Console\Commands\Version;
use Assegai\Console\Tests\Feature\Support\VersionWorkspaceFactory;
use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

describe('Version command', function () {
  it('reports the running CLI version outside a valid workspace', function () {
    $workspace = sys_get_temp_dir() . '/' . uniqid('console-invalid-version-', true);

    if (!mkdir($workspace, 0755, true) && !is_dir($workspace)) {
      throw new RuntimeException("Failed to create test workspace: $workspace");
    }

    try {
      $tester = new CommandTester(new Version());
      $status = $tester->execute([
        '--directory' => $workspace,
      ]);
      $display = $tester->getDisplay();

      expect($status)->toBe(Command::SUCCESS);
      expect($display)
        ->toContain('CLI Version:')
        ->toContain(Inspector::getRunningCLIVersion());
      expect(str_contains($display, 'Assegai Version'))->toBeFalse();
    } finally {
      VersionWorkspaceFactory::remove($workspace);
    }
  });

  it('reports the installed Assegai version from workspace composer metadata', function () {
    $workspace = VersionWorkspaceFactory::create('0.8.0', 'installed');

    try {
      $tester = new CommandTester(new Version());
      $status = $tester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($tester->getDisplay())
        ->toContain('CLI Version:')
        ->toContain('Installed Assegai Version:')
        ->toContain('0.8.0');
    } finally {
      VersionWorkspaceFactory::remove($workspace);
    }
  });

  it('falls back to the locked Assegai version when vendor metadata is unavailable', function () {
    $workspace = VersionWorkspaceFactory::create('0.9.0', 'lock');

    try {
      $tester = new CommandTester(new Version());
      $status = $tester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($tester->getDisplay())
        ->toContain('CLI Version:')
        ->toContain('Locked Assegai Version:')
        ->toContain('0.9.0');
    } finally {
      VersionWorkspaceFactory::remove($workspace);
    }
  });
});
