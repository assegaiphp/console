<?php

namespace Assegai\Console\Commands\Database;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DatabaseSetup. This class is a command that sets up the database.
 */
#[AsCommand(
    name: 'database:setup',
    description: 'Setup the database',
    aliases: ['db:setup']
)]
class DatabaseSetup extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('name', InputArgument::REQUIRED, 'The name of the database')
      ->addOption('type', 't', InputArgument::OPTIONAL, 'The type of the database', 'mysql');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement execute() method.

    return Command::SUCCESS;
  }
}