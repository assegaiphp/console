<?php

namespace Assegai\Console\Commands\Migration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migration:up',
    description: 'Run the migrations',
    aliases: ['migration:run', 'migrate:up']
)]
class MigrationUp extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement the up logic

    return Command::SUCCESS;
  }
}