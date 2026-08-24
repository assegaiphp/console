<?php

namespace Assegai\Console;

use Assegai\Console\Util\CliInputNormalizer;
use Assegai\Console\Util\CliVersionChecker;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class AssegaiApplication extends Application
{
  /** @param string[]|null $argv */
  public function __construct(
    private readonly CliVersionChecker $versionChecker,
    private readonly ?array $argv = null,
  ) {
    parent::__construct('Assegai CLI', $versionChecker->getCurrentVersion());
  }

  public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
  {
    $input ??= new ArgvInput(CliInputNormalizer::normalize($this->argv ?? ($_SERVER['argv'] ?? [])));
    $output ??= new ConsoleOutput();

    $this->notifyAboutAvailableUpdate($input, $output);

    return parent::run($input, $output);
  }

  private function notifyAboutAvailableUpdate(InputInterface $input, OutputInterface $output): void
  {
    if (
      $this->versionCheckIsDisabled() ||
      $input->hasParameterOption(['--silent', '-q', '--quiet'], true)
    ) {
      return;
    }

    $commandName = $input->getFirstArgument();

    if (in_array($commandName, ['global:update', 'self:update', '_complete'], true)) {
      return;
    }

    try {
      $availableVersion = $this->versionChecker->findAvailableUpdate();
    } catch (Throwable) {
      return;
    }

    if ($availableVersion === null) {
      return;
    }

    $notificationOutput = $output instanceof ConsoleOutputInterface
      ? $output->getErrorOutput()
      : $output;

    $notificationOutput->writeln(sprintf(
      '<comment>Assegai CLI %s is available (you have %s). Run `assegai global update` or `assegai -g update`.</comment>',
      $availableVersion,
      $this->versionChecker->getCurrentVersion(),
    ));
  }

  private function versionCheckIsDisabled(): bool
  {
    $value = getenv('ASSEGAI_NO_UPDATE_CHECK');

    if (! is_string($value)) {
      return false;
    }

    return ! in_array(strtolower(trim($value)), ['', '0', 'false', 'no', 'off'], true);
  }
}
