<?php

use Assegai\Console\Commands\GlobalUpdate;
use Assegai\Console\Util\CliVersionChecker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function globalUpdateMetadata(string ...$versions): string
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

function globalUpdateChecker(string $currentVersion, false|string $metadata): CliVersionChecker
{
  return new CliVersionChecker(
    $currentVersion,
    sys_get_temp_dir() . '/' . uniqid('global-update-', true) . '/version.json',
    static fn (string $url): false|string => $metadata,
  );
}

class TestableGlobalUpdateCommand extends GlobalUpdate
{
  public ?string $requestedVersion = null;
  public ?bool $dryRun = null;
  public ?bool $nonInteractive = null;
  public bool $composerWasRun = false;

  public function __construct(CliVersionChecker $checker, private readonly int $composerStatus)
  {
    parent::__construct($checker);
  }

  protected function composerIsInstalled(): bool
  {
    return true;
  }

  protected function runComposerGlobalUpdate(?string $latestVersion, bool $dryRun, bool $nonInteractive): int
  {
    $this->composerWasRun = true;
    $this->requestedVersion = $latestVersion;
    $this->dryRun = $dryRun;
    $this->nonInteractive = $nonInteractive;

    return $this->composerStatus;
  }
}

function testableGlobalUpdate(CliVersionChecker $checker, int $composerStatus = Command::SUCCESS): TestableGlobalUpdateCommand
{
  return new TestableGlobalUpdateCommand($checker, $composerStatus);
}

describe('GlobalUpdate', function () {
  it('updates the global CLI across release lines', function () {
    $command = testableGlobalUpdate(globalUpdateChecker('0.9.5', globalUpdateMetadata('0.9.5', '0.10.1')));
    $tester = new CommandTester($command);

    expect($tester->execute([]))->toBe(Command::SUCCESS)
      ->and($command->composerWasRun)->toBeTrue()
      ->and($command->requestedVersion)->toBe('0.10.1')
      ->and($command->dryRun)->toBeFalse()
      ->and($tester->getDisplay(true))->toContain('Updating Assegai CLI from 0.9.5 to 0.10.1')
      ->toContain('Global Assegai CLI update complete');
  });

  it('does not invoke Composer when the CLI is current', function () {
    $command = testableGlobalUpdate(globalUpdateChecker('0.10.1', globalUpdateMetadata('0.10.1')));
    $tester = new CommandTester($command);

    expect($tester->execute([]))->toBe(Command::SUCCESS)
      ->and($command->composerWasRun)->toBeFalse()
      ->and($tester->getDisplay(true))->toContain('already up to date (0.10.1)');
  });

  it('lets Composer resolve the latest release when metadata lookup fails', function () {
    $command = testableGlobalUpdate(globalUpdateChecker('0.9.5', false));
    $tester = new CommandTester($command);

    expect($tester->execute([]))->toBe(Command::SUCCESS)
      ->and($command->composerWasRun)->toBeTrue()
      ->and($command->requestedVersion)->toBeNull()
      ->and($tester->getDisplay(true))->toContain('Composer will resolve the newest compatible CLI release');
  });

  it('supports a non-mutating dry run', function () {
    $command = testableGlobalUpdate(globalUpdateChecker('0.9.5', globalUpdateMetadata('0.10.1')));
    $tester = new CommandTester($command);

    expect($tester->execute(['--dry-run' => true]))->toBe(Command::SUCCESS)
      ->and($command->dryRun)->toBeTrue()
      ->and($tester->getDisplay(true))->toContain('Dry run complete');
  });

  it('passes non-interactive execution through to Composer', function () {
    $command = testableGlobalUpdate(globalUpdateChecker('0.9.5', globalUpdateMetadata('0.10.1')));
    $tester = new CommandTester($command);

    expect($tester->execute([], ['interactive' => false]))->toBe(Command::SUCCESS)
      ->and($command->nonInteractive)->toBeTrue();
  });

  it('reports Composer failures', function () {
    $command = testableGlobalUpdate(
      globalUpdateChecker('0.9.5', globalUpdateMetadata('0.10.1')),
      Command::FAILURE,
    );
    $tester = new CommandTester($command);

    expect($tester->execute([]))->toBe(Command::FAILURE)
      ->and($tester->getDisplay(true))->toContain('Composer could not update the global Assegai CLI');
  });
});
