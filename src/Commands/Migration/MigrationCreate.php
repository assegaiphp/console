<?php

namespace Assegai\Console\Commands\Migration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migration:create',
    description: 'Create a new migration',
    aliases: ['migrate:create']
)]
class MigrationCreate extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement the create logic

    return Command::SUCCESS;
  }
}