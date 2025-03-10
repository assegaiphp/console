<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\SQLDatabaseConnectionInterface;
use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Database\PostgreSQLDatabase;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
  name: 'migration:setup',
  description: 'Setup the migrations',
  aliases: ['m:setup', 'migration:init']
)]
class MigrationSetup extends Command
{
  public function configure(): void
  {
    $this
      ->setHelp('This command sets up the migrations table in the database and creates the migrations directory ' .
      'in the project root')
      ->addArgument('database', InputArgument::REQUIRED, 'The name of the database')
      ->addOption('database_type', 'dt', InputArgument::OPTIONAL, 'The type of the database', DEFAULT_DATABASE_TYPE, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use MySQL database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use SQLite database');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    /** @var QuestionHelper $helper */
    $helper = $this->getHelper('question');

    // Get project root
    $inspector = new Inspector($input, $output);
    $root = getcwd() ?: '';

    if (! $inspector->isValidWorkspace($root) )
    {
      $output->writeln("<error>Invalid workspace</error>\n");
      return Command::FAILURE;
    }

    $migrationsDirectory = Path::join($root, 'migrations');

    // Create a migrations directory for the specific database type
    $defaultDatabaseType = 'mysql';
    $databaseType = match(true)  {
      $input->getOption(DatabaseType::MYSQL->value) => DatabaseType::MYSQL->value,
      $input->getOption(DatabaseType::POSTGRESQL->value) => DatabaseType::POSTGRESQL->value,
      $input->getOption(DatabaseType::SQLITE->value) => DatabaseType::SQLITE->value,
      default =>
        $input->getOption('database_type') ??
        $helper->ask($input, $output, new Question("<info>?</info> Enter the database type: <fg=gray>($defaultDatabaseType)</> ", 'mysql'))
    };

    // Create a migrations directory for the specific database
    $databaseName =
      $input->getArgument('database') ??
      $helper->ask($input, $output, new Question("<info>?</info> Enter the database name: "));

    $migrationsDirectory = Path::join($migrationsDirectory, $databaseType, $databaseName);

    $output->writeln("<comment>Setting up migrations for <info>$databaseType:$databaseName</info>...</comment>\n");

    // Check if the migrations directory exists in the project root
    if (! is_dir($migrationsDirectory) )
    {
      // If it does not exist, create it
      if (false === mkdir($migrationsDirectory, 0777, true) )
      {
        $output->writeln("<error>Failed to create the migrations directory</error>\n");
        return Command::FAILURE;
      }

      $output->writeln("📂 Migrations directory created successfully\n");
    }
    else
    {
      $output->writeln("<comment>The migrations directory already exists</comment>\n");
    }

    # Migrations table setup
    $dbConfig = new DBCOnfig($input, $output, $databaseName, $databaseType);

    if (Command::SUCCESS !== $dbConfig->load() )
    {
      $output->writeln("<error>Failed to load database configuration</error>\n");
      return Command::FAILURE;
    }

    /** @var SQLDatabaseConnectionInterface $database */
    $database = match ($databaseType) {
      DatabaseType::SQLITE->value => new SQLiteDatabase($databaseName, $input, $output),
      DatabaseType::POSTGRESQL->value => new PostgreSQLDatabase($databaseName, $input, $output),
      default => new MySQLDatabase($databaseName, $input, $output)
    };

    if (! $database->hasTable($database::getMigrationsTableName()) )
    {
      if (Command::SUCCESS !== $database->createMigrationsTable() )
      {
        $output->writeln("<error>Failed to create the migrations table</error>\n");
        return Command::FAILURE;
      }

      $output->writeln("🏗️ Migrations table created successfully\n");
    }

    $output->writeln("✔️  Migrations setup completed successfully\n");
    return Command::SUCCESS;
  }
}