<?php

namespace Assegai\Console\Commands\Migration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migration:refresh',
    description: 'Refresh the migrations',
    aliases: ['migrate:refresh']
)]
class MigrationRefresh extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement the refresh logic

    return Command::SUCCESS;
  }
}