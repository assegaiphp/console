<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Core\Database\MariaDbDatabase;
use Assegai\Console\Core\Database\MsSqlDatabase;
use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Database\PostgreSQLDatabase;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Assegai\Console\Util\Inspector;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
      ->addArgument(ParameterKey::DB_NAME->value, InputArgument::REQUIRED, 'The name of the database')
      ->addOption(ParameterKey::DB_TYPE->value, ParameterKey::DB_TYPE->getShortName(), InputArgument::OPTIONAL, 'The type of the database', DEFAULT_DATABASE_TYPE, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use MySQL database')
      ->addOption(DatabaseType::MARIADB->value, null, InputOption::VALUE_NONE, 'Use MariaDB database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use SQLite database')
      ->addOption(DatabaseType::MSSQL->value, null, InputOption::VALUE_NONE, 'Use MSSQL database');
  }

  /**
   * @throws Exception
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);

    if (! $inspector->isValidWorkspace(getcwd() ?: '')) {
      $output->writeln("<error>Invalid workspace.</error>\n");
      return Command::FAILURE;
    }

    $name = $input->getArgument(ParameterKey::DB_NAME->value);
    $type = get_datasource_type($input, $output)
      ?: throw new Exception('Database type is not specified. Use the --db-type option to specify the database type.');

    if (! DatabaseType::isValid($type)) {
      $output->writeln("<error>Invalid database type.</error>\n");
      return Command::FAILURE;
    }

    /** @var class-string<DatabaseConnectionInterface> $databaseClass */
    $databaseClass = match ($type) {
      DatabaseType::SQLITE->value => SQLiteDatabase::class,
      DatabaseType::POSTGRESQL->value => PostgreSQLDatabase::class,
      DatabaseType::MARIADB->value => MariaDbDatabase::class,
      DatabaseType::MSSQL->value => MsSqlDatabase::class,
      default => MySQLDatabase::class,
    };

    if (Command::SUCCESS !== $databaseClass::setup($name)) {
      $output->writeln("<error>Failed to create the database.</error>\n");
      return Command::FAILURE;
    }

    new $databaseClass($name, $input, $output);

    $output->writeln("✔️  Database <info>$name</info>, successfully setup!.");
    return Command::SUCCESS;
  }
}
