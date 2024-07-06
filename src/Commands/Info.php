<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
  name: 'info',
  description: 'Output information about the application.',
  aliases: ['i']
)]
class Info extends Command
{
  public function configure(): void
  {
    $this->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to inspect.', getcwd());
  }

  /**
   * @throws Throwable
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    if ($headerContent = file_get_contents(__DIR__ . '/../../assets/header.txt') ) {
      $output->writeln("<fg=red>$headerContent</>");
    }

    /** @var FormatterHelper $formatter */
    $formatter = $this->getHelper('formatter');
    $inspector = new Inspector($input, $output);
    $directory = $input->getOption('directory');
    $osFamily = PHP_OS_FAMILY;
    if (PHP_EXTRA_VERSION) {
      $osFamily .= " (" . PHP_EXTRA_VERSION . ")";
    }
    $phpVersion = PHP_VERSION;

    # Output information about the system
    $output->writeln("\n" . $formatter->formatBlock('Platform Info', 'question') . "\n");
    $output->writeln("<info>OS Family:</info> $osFamily");
    $output->writeln("<info>PHP Version:</info> $phpVersion");

    # Output information about the application
    if ($inspector->isValidWorkspace($directory)) {
      $assegaiVersion = $inspector->getInstalledFrameworkVersion($directory);
      $output->writeln("<info>Assegai Version:</info> $assegaiVersion");
    }

    # Output information about the framework
    if ($inspector->packageIsInstalledGlobally(PACKAGE_NAME_CLI)) {
      $cliVersion = $inspector->getCLIVersion();
      $output->writeln("<info>CLI Version:</info> $cliVersion");
    }

    # Output information about the available commands
    $output->writeln("\n" . $formatter->formatBlock('Commands', 'question') . "\n");

    return $this->getApplication()?->doRun(new ArrayInput([
      'command' => 'list'
    ]), $output) ?? Command::INVALID;
  }
}