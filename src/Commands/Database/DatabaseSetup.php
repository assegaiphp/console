<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Database\PostgreSQLDatabase;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
    // Check if the workspace is valid
    $inspector = new Inspector($input, $output);
    /** @var QuestionHelper $helper */
    $helper = $this->getHelper('question');

    if (! $inspector->isValidWorkspace(getcwd() ?: '') )
    {
      $output->writeln('<error>Invalid workspace.</error>');
      return Command::FAILURE;
    }

    // Check if the database configuration exists
    $name = $input->getArgument('name');
    $type = $input->getOption('type');

    if (! DatabaseType::isValid($type))
    {
      $output->writeln('<error>Invalid database type.</error>');
      return Command::FAILURE;
    }

    // Check if the database exists
    $databaseConnectionClass = match($type) {
      DatabaseType::SQLITE->value => SQLiteDatabase::class,
      DatabaseType::POSTGRESQL->value => PostgreSQLDatabase::class,
      default => MySQLDatabase::class
    };

    if ($databaseConnectionClass::exists($name))
    {
      $output->writeln('<info>Database exists.</info>');
      return Command::SUCCESS;
    }

    /** @var DatabaseConnectionInterface $database */
    $database = new $databaseConnectionClass($name, $input, $output);

    // If the database does not exist, then ask the user to create it
    $output->writeln('<info>Database does not exist. Creating database...</info>');
    $answer = $helper->ask($input, $output, new ConfirmationQuestion('<info>?</info> Do you want to create the database? <fg=gray>(Y/n)</> '));

    if (!$answer)
    {
      if ($databaseConnectionClass === SQLiteDatabase::class)
      {
        $database->drop();
      }

      $output->writeln('<error>Database not created.</error>');
      return Command::FAILURE;
    }

    // Create the database
    if (Command::SUCCESS !== $database->setup())
    {
      $output->writeln('<error>Failed to create the database.</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}