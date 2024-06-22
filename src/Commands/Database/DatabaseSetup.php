<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Database\PostgreSQLDatabase;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Util\Inspector;
use Exception;
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
      $output->writeln("<error>Invalid workspace.</error>\n");
      return Command::FAILURE;
    }

    // Check if the database configuration exists
    $name = $input->getArgument('name');
    $type = $input->getOption('type');

    if (! DatabaseType::isValid($type) )
    {
      $output->writeln("<error>Invalid database type.</error>\n");
      return Command::FAILURE;
    }

    // Check if the database exists
    $databaseClass = match($type) {
      DatabaseType::SQLITE->value => SQLiteDatabase::class,
      DatabaseType::POSTGRESQL->value => PostgreSQLDatabase::class,
      default => MySQLDatabase::class
    };

    if ($type === DatabaseType::POSTGRESQL->value)
    {
      // Not implemented
      $output->writeln("<error>PostgreSQL not implemented.</error>\n");
      return Command::SUCCESS;
    }

    // Create the database
    if (Command::SUCCESS !== $databaseClass::setup($name))
    {
      $output->writeln("<error>Failed to create the database.</error>\n");
      return Command::FAILURE;
    }

    try
    {
      // Check if the database connection is successful
      /** @var DatabaseConnectionInterface $database */
      $database = new $databaseClass($name, $input, $output);
    }
    catch (Exception $exception)
    {
      $message = match ($exception->getCode() ) {
        MySQLDatabase::ERROR_UNKNOWN_DATABASE => 'Database not found',
        MySQLDatabase::ERROR_INVALID_CREDENTIALS => 'Invalid credentials',
        default => $exception->getMessage()
      };

      $output->writeln("<error>({$exception->getCode()}): $message</error>\n");
      return Command::FAILURE;
    }

    $output->writeln("✔️  Database <info>$name</info>, successfully setup!.");
    return Command::SUCCESS;
  }
}