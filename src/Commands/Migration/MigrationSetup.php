<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
  name: 'migration:setup',
  description: 'Setup the migrations',
  aliases: ['migration:init, migrate:setup']
)]
class MigrationSetup extends Command
{
  public function configure(): void
  {
    $this->setHelp('This command sets up the migrations table in the database and creates the migrations directory ' .
      'in the project root');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $output->writeln('Setting up the migrations');

    /** @var QuestionHelper $helper */
    $helper = $this->getHelper('question');

    // Get project root
    $inspector = new Inspector($input, $output);
    $root = getcwd() ?: '';

    if (! $inspector->isValidWorkspace($root) )
    {
      $output->writeln([
        '',
        '<error>Invalid workspace</error>',
        ''
      ]);
      return Command::FAILURE;
    }

    $migrationsDirectory = Path::join($root, 'migrations');

    // Create a migrations directory for the specific database type
    $defaultDatabaseType = 'mysql';
    $databaseType = $helper->ask($input, $output, new Question("<info>?</info> Enter the database type: <fg=gray>($defaultDatabaseType)</> ", 'mysql'));

    // Create a migrations directory for the specific database
    $databaseName = $helper->ask($input, $output, new Question('<info>?</info> Enter the database name: '));

    $migrationsDirectory = Path::join($migrationsDirectory, $databaseType, $databaseName);

    // Check if the migrations directory exists in the project root
    if (! is_dir($migrationsDirectory))
    {
      // If it does not exist, create it
      if (false === mkdir($migrationsDirectory, 0777, true) )
      {
        $output->writeln('<error>Failed to create the migrations directory</error>');
        return Command::FAILURE;
      }

      $output->writeln([
        '',
        '<info>Created the migrations directory</info>',
        ''
      ]);
    }
    else
      $output->writeln([
        '',
        '<comment>The migrations directory already exists</comment>',
        ''
      ]);
    {
    }

    // Check if the migrations table exists in the database
    // TODO: Implement the logic to check if the migrations table exists in the database

    // If it does not exist, create it
    // TODO: Implement the logic to create the migrations table in the database

    return Command::SUCCESS;
  }
}