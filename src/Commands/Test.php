<?php

namespace Assegai\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
  name: 'test',
  description: 'Run unit tests in a project.',
  aliases: ['t'],
)]
class Test extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this->setHelp("This command runs unit tests in a project. It is an alias for the assegai test runner. Once executed, it will run all tests in the project.");
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $process = new Process(['composer', 'test', '--ansi']);
    $process->run();

    if (!$process->isSuccessful()) {
      $output->writeln('<error>Tests failed.</error>');
      return Command::FAILURE;
    }

    $output->writeln($process->getOutput());

    return Command::SUCCESS;
  }
}