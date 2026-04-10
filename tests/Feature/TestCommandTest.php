<?php

use Assegai\Console\Commands\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

describe('Test command', function () {
  it('runs composer tests without requiring symfony process', function () {
    $command = new class () extends Test {
      protected function runComposerTests(): array
      {
        return [
          'status' => Command::SUCCESS,
          'stdout' => "All tests passed\n",
          'stderr' => '',
        ];
      }
    };

    $tester = new CommandTester($command);
    $status = $tester->execute([]);

    expect($status)->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain('All tests passed');
  });

  it('preserves ANSI escape codes from the underlying test runner output', function () {
    $ansiOutput = "\033[32mAll tests passed\033[0m\n";

    $command = new class ($ansiOutput) extends Test {
      public function __construct(private readonly string $ansiOutput)
      {
        parent::__construct();
      }

      protected function runComposerTests(): array
      {
        return [
          'status' => Command::SUCCESS,
          'stdout' => $this->ansiOutput,
          'stderr' => '',
        ];
      }
    };

    $tester = new CommandTester($command);
    $status = $tester->execute([], ['decorated' => true]);

    expect($status)->toBe(Command::SUCCESS);
    expect($tester->getDisplay())->toContain("\033[32mAll tests passed\033[0m");
  });

  it('prints a helpful failure message when composer tests fail', function () {
    $command = new class () extends Test {
      protected function runComposerTests(): array
      {
        return [
          'status' => Command::FAILURE,
          'stdout' => '',
          'stderr' => "Composer test failed\n",
        ];
      }
    };

    $tester = new CommandTester($command);
    $status = $tester->execute([]);

    expect($status)->toBe(Command::FAILURE);
    expect($tester->getDisplay())
      ->toContain('Composer test failed')
      ->toContain('Tests failed.');
  });
});
