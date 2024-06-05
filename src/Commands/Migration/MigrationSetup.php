<?php

namespace Assegai\Console\Commands\Migration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'migration:setup',
  description: 'Setup the migrations',
  aliases: ['migration:init, migrate:setup']
)]
class MigrationSetup extends Command
{
  public function configure(): void
  {
    $this->setHelp('This command sets up the migrations. It creates the migrations table in the database and ' .
      'sets up the migrations directory')
      ->addOption('dir', 'd', InputArgument::OPTIONAL, 'The directory where the migrations will be stored', 'migrations');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $output->writeln('Setting up the migrations');
    return Command::SUCCESS;
  }
}