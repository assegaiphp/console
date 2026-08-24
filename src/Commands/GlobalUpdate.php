<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\CliVersionChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'global:update',
  description: 'Updates the globally installed Assegai CLI.',
  aliases: ['self:update'],
)]
class GlobalUpdate extends Command
{
  public function __construct(private readonly CliVersionChecker $versionChecker)
  {
    parent::__construct();
  }

  public function configure(): void
  {
    $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the global CLI update without changing the installation');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    if (! $this->composerIsInstalled()) {
      $output->writeln('<error>Composer is required to update the global Assegai CLI.</error>');
      return Command::FAILURE;
    }

    $dryRun = (bool) $input->getOption('dry-run');
    $currentVersion = $this->versionChecker->getCurrentVersion();
    $latestVersion = $this->versionChecker->getLatestVersion(true);

    if (
      $latestVersion !== null &&
      preg_match('/^v?\d+\.\d+\.\d+$/i', $currentVersion) === 1 &&
      version_compare(ltrim($currentVersion, 'vV'), $latestVersion, '>=')
    ) {
      $output->writeln(sprintf('<info>Assegai CLI is already up to date (%s).</info>', $currentVersion));
      return Command::SUCCESS;
    }

    if ($latestVersion === null) {
      $output->writeln('<comment>The version service is unavailable; Composer will resolve the newest compatible CLI release.</comment>');
    } else {
      $output->writeln(sprintf(
        '<info>%s Assegai CLI from %s to %s...</info>',
        $dryRun ? 'Previewing an update of' : 'Updating',
        $currentVersion,
        $latestVersion,
      ));
    }

    $status = $this->runComposerGlobalUpdate($latestVersion, $dryRun, ! $input->isInteractive());

    if ($status !== Command::SUCCESS) {
      $output->writeln('<error>Composer could not update the global Assegai CLI.</error>');
      return Command::FAILURE;
    }

    if ($dryRun) {
      $output->writeln('<info>Dry run complete. The global CLI installation was not changed.</info>');
      return Command::SUCCESS;
    }

    $this->versionChecker->clearCache();
    $output->writeln('<info>Global Assegai CLI update complete. Run `assegai --version` to verify the installed version.</info>');

    return Command::SUCCESS;
  }

  protected function composerIsInstalled(): bool
  {
    return is_installed('composer');
  }

  protected function runComposerGlobalUpdate(?string $latestVersion, bool $dryRun, bool $nonInteractive): int
  {
    $package = $latestVersion === null
      ? PACKAGE_NAME_CLI
      : sprintf('%s:^%s', PACKAGE_NAME_CLI, $latestVersion);
    $options = ['--with-all-dependencies'];

    if ($dryRun) {
      $options[] = '--dry-run';
    }

    if ($nonInteractive) {
      $options[] = '--no-interaction';
    }

    $options[] = '--ansi';
    $command = sprintf(
      'composer global require %s %s',
      escapeshellarg($package),
      implode(' ', $options),
    );

    passthru($command, $statusCode);

    return $statusCode;
  }
}
