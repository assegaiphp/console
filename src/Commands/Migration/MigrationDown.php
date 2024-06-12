<?php

namespace Assegai\Console\Commands\Migration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migration:down',
    description: 'Rollback the migrations',
    aliases: ['migration:rollback', 'migrate:down']
)]
class MigrationDown extends Command
{

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement the down logic

    return Command::SUCCESS;
  }
}