<?php

use Assegai\Console\AssegaiApplication;
use Assegai\Console\Util\CliVersionChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

function notificationVersionMetadata(string ...$versions): string
{
  return (string) json_encode([
    'packages' => [
      PACKAGE_NAME_CLI => array_map(
        static fn (string $version): array => ['version' => $version],
        $versions,
      ),
    ],
  ], JSON_UNESCAPED_SLASHES);
}

function notificationApplication(string $currentVersion, false|string $metadata): AssegaiApplication
{
  $checker = new CliVersionChecker(
    $currentVersion,
    sys_get_temp_dir() . '/' . uniqid('update-notification-', true) . '/version.json',
    static fn (string $url): false|string => $metadata,
  );
  $application = new AssegaiApplication($checker);
  $application->setAutoExit(false);
  $application->addCommand(new class('demo') extends Command {
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
      $output->writeln('command output');
      return Command::SUCCESS;
    }
  });
  $application->addCommand(new class('global:update') extends Command {
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
      $output->writeln('global update output');
      return Command::SUCCESS;
    }
  });

  return $application;
}

describe('AssegaiApplication update notification', function () {
  it('writes an available version notice to stderr without polluting command output', function () {
    $tester = new ApplicationTester(notificationApplication('0.9.5', notificationVersionMetadata('0.10.1')));

    expect($tester->run(
      ['command' => 'demo'],
      ['capture_stderr_separately' => true],
    ))->toBe(Command::SUCCESS)
      ->and($tester->getDisplay(true))->toBe("command output\n")
      ->and($tester->getErrorOutput(true))->toContain('Assegai CLI 0.10.1 is available (you have 0.9.5)')
      ->toContain('assegai global update')
      ->toContain('assegai -g update');
  });

  it('continues silently when the version service is unavailable', function () {
    $tester = new ApplicationTester(notificationApplication('0.9.5', false));

    expect($tester->run(
      ['command' => 'demo'],
      ['capture_stderr_separately' => true],
    ))->toBe(Command::SUCCESS)
      ->and($tester->getDisplay(true))->toBe("command output\n")
      ->and($tester->getErrorOutput(true))->toBe('');
  });

  it('does not check or notify when quiet output is requested', function () {
    $requests = 0;
    $checker = new CliVersionChecker(
      '0.9.5',
      sys_get_temp_dir() . '/' . uniqid('quiet-notification-', true) . '/version.json',
      static function (string $url) use (&$requests): string {
        $requests++;
        return notificationVersionMetadata('0.10.1');
      },
    );
    $application = new AssegaiApplication($checker);
    $application->setAutoExit(false);
    $application->addCommand(new class('demo') extends Command {
      protected function execute(InputInterface $input, OutputInterface $output): int
      {
        return Command::SUCCESS;
      }
    });
    $tester = new ApplicationTester($application);

    expect($tester->run(['command' => 'demo', '--quiet' => true]))->toBe(Command::SUCCESS)
      ->and($requests)->toBe(0);
  });

  it('does not duplicate the check for the global update command', function () {
    $requests = 0;
    $checker = new CliVersionChecker(
      '0.9.5',
      sys_get_temp_dir() . '/' . uniqid('global-notification-', true) . '/version.json',
      static function (string $url) use (&$requests): string {
        $requests++;
        return notificationVersionMetadata('0.10.1');
      },
    );
    $application = new AssegaiApplication($checker);
    $application->setAutoExit(false);
    $application->addCommand(new class('global:update') extends Command {
      protected function execute(InputInterface $input, OutputInterface $output): int
      {
        return Command::SUCCESS;
      }
    });
    $tester = new ApplicationTester($application);

    expect($tester->run(['command' => 'global:update']))->toBe(Command::SUCCESS)
      ->and($requests)->toBe(0);
  });
});
