<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Commands\Database\Enumerations\DatabaseType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:load',
    description: 'Load a schema.sql file to the database',
    aliases: ['db:load']
)]
class DatabaseLoad extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('database', InputArgument::REQUIRED, 'The name of the database')
      ->addArgument('file', InputArgument::REQUIRED, 'The path to the schema.sql file')
      ->addOption('type', 't', InputArgument::OPTIONAL, 'The type of the database', 'mysql');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $database = $input->getArgument('database');
    $file = $input->getArgument('file');
    $type = $input->getOption('type');

    if (! file_exists($file))
    {
      $output->writeln('<error>File not found</error>');
      return Command::FAILURE;
    }

    if (! DatabaseType::isValid($input->getOption('type') ?? ''))
    {
      $output->writeln('<error>Invalid database type</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}