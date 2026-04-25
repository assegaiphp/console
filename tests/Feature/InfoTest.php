<?php

use Assegai\Console\Commands\Info;
use Assegai\Console\Tests\Feature\Support\VersionWorkspaceFactory;
use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

describe('Info', function () {
  it('reports the running CLI version and installed assegai version for a valid workspace', function () {
    $workspace = VersionWorkspaceFactory::create('0.8.0', 'installed');

    try {
      $application = new Application('test');
      $application->addCommands([new Info()]);

      $tester = new CommandTester($application->find('info'));
      $status = $tester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($tester->getDisplay())
        ->toContain('Platform Info')
        ->toContain('CLI Version:')
        ->toContain(Inspector::getRunningCLIVersion())
        ->toContain('Installed Assegai Version:')
        ->toContain('0.8.0')
        ->toContain('Commands');
    } finally {
      VersionWorkspaceFactory::remove($workspace);
    }
  });
});
