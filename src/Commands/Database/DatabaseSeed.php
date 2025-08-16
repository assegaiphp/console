<?php

namespace Assegai\Console\Commands\Database;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:seed',
    description: 'Seed the database',
    aliases: ['db:seed']
)]
class DatabaseSeed extends Command
{
  public function configure(): void
  {
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $output->writeln("<info>No database seeding implemented yet.</info>");
    return Command::SUCCESS;
  }
}