<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\SQLDatabaseConnectionInterface;
use Assegai\Console\Core\Database\MariaDbDatabase;
use Assegai\Console\Core\Database\MsSqlDatabase;
use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Database\PostgreSQLDatabase;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
      ->setHelp('This command sets up the migrations table in the database and creates the migrations directory in the project root')
      ->addArgument(ParameterKey::DB_NAME->value, InputArgument::REQUIRED, 'The name of the database')
      ->addOption(ParameterKey::DB_TYPE->value, ParameterKey::DB_TYPE->getShortName(), InputArgument::OPTIONAL, 'The type of the database', DEFAULT_DATABASE_TYPE, DatabaseType::toArray())
      ->addOption('host', 'H', InputArgument::OPTIONAL, 'The host of the database when creating a missing connection config')
      ->addOption('port', 'P', InputArgument::OPTIONAL, 'The port of the database when creating a missing connection config')
      ->addOption('user', 'u', InputArgument::OPTIONAL, 'The user of the database when creating a missing connection config')
      ->addOption('password', 'p', InputArgument::OPTIONAL, 'The password of the database when creating a missing connection config')
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use MySQL database')
      ->addOption(DatabaseType::MARIADB->value, null, InputOption::VALUE_NONE, 'Use MariaDB database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use SQLite database')
      ->addOption(DatabaseType::MSSQL->value, null, InputOption::VALUE_NONE, 'Use MSSQL database');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $prompts = new CliPrompt($input, $output);
    $inspector = new Inspector($input, $output);
    $root = getcwd() ?: '';

    if (! $inspector->isValidWorkspace($root)) {
      $output->writeln("<error>Invalid workspace</error>\n");
      return Command::FAILURE;
    }

    $migrationsDirectory = Path::join($root, 'migrations');
    $databaseName = (string) (
      $input->getArgument(ParameterKey::DB_NAME->value) ??
      $prompts->text('Enter the database name')
    );
    $databaseType = get_datasource_type($input, $output);

    if (! $databaseType || ! DatabaseType::isValid($databaseType)) {
      $output->writeln("<error>Invalid database type</error>\n");
      return Command::FAILURE;
    }

    if (! has_configured_datasource($databaseType, $databaseName)) {
      $output->writeln("<comment>Creating database configuration for <info>$databaseType:$databaseName</info>...</comment>\n");

      if (Command::SUCCESS !== configure_datasource($input, $output, $databaseType, $databaseName, $root, false)) {
        return Command::FAILURE;
      }
    }

    $migrationsDirectory = Path::join($migrationsDirectory, $databaseType, $databaseName);

    $output->writeln("<comment>Setting up migrations for <info>$databaseType:$databaseName</info>...</comment>\n");

    if (! is_dir($migrationsDirectory)) {
      if (false === mkdir($migrationsDirectory, 0777, true)) {
        $output->writeln("<error>Failed to create the migrations directory</error>\n");
        return Command::FAILURE;
      }

      $output->writeln("📂 Migrations directory created successfully\n");
    } else {
      $output->writeln("<comment>The migrations directory already exists</comment>\n");
    }

    /** @var SQLDatabaseConnectionInterface $database */
    $database = match ($databaseType) {
      DatabaseType::SQLITE->value => new SQLiteDatabase($databaseName, $input, $output),
      DatabaseType::POSTGRESQL->value => new PostgreSQLDatabase($databaseName, $input, $output),
      DatabaseType::MARIADB->value => new MariaDbDatabase($databaseName, $input, $output),
      DatabaseType::MSSQL->value => new MsSqlDatabase($databaseName, $input, $output),
      default => new MySQLDatabase($databaseName, $input, $output)
    };

    if (! $database->hasTable($database::getMigrationsTableName())) {
      if (Command::SUCCESS !== $database->createMigrationsTable()) {
        $output->writeln("<error>Failed to create the migrations table</error>\n");
        return Command::FAILURE;
      }

      $output->writeln("🏗️ Migrations table created successfully\n");
    }

    $output->writeln("✔️  Migrations setup completed successfully\n");
    return Command::SUCCESS;
  }
}
