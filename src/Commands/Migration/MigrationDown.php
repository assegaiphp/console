<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\PostgreSQLDatabase;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Core\Database\Traits\DatabaseNameValidatorTrait;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Assegai\Console\Core\Migrations\MySQLDatabaseMigrator;
use Assegai\Console\Util\Config\AppConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\select;

#[AsCommand(
    name: 'migration:down',
    description: 'Rollback the migrations',
    aliases: ['m:down', 'migration:rollback', 'migrate:down']
)]
class MigrationDown extends Command
{
  use DatabaseNameValidatorTrait;

  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this
      ->addArgument('database', InputArgument::REQUIRED, 'The database to rollback the migrations on')
      ->addOption('database_type', 'dt', InputArgument::OPTIONAL, 'The type of the database', DEFAULT_DATABASE_TYPE)
      ->addOption('steps', 's', InputArgument::OPTIONAL, 'The number of migrations to rollback', 1)
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Run the migrations on a MySQL database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Run the migrations on a PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Run the migrations on a SQLite database');
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);
    $workingDirectory = getcwd() ?: '';
    /** @var QuestionHelper $helper */
    $helper = $this->getHelper('question');
    $appConfig = new AppConfig($input, $output);

    if (Command::SUCCESS !== $appConfig->load()) {
      $output->writeln('<error>Failed to load the configuration file</error>');
      return Command::FAILURE;
    }

    if ($inspector->isNotAValidWorkspace($workingDirectory)) {
      $output->writeln("<error>Invalid workspace.</error>\n");
      return Command::FAILURE;
    }

    $databaseType = $input->getOption('database_type');
    $dbName = $input->getArgument('database');

    if (! $databaseType || ! DatabaseType::isValid($databaseType)) {
      $databaseTypes = DatabaseType::toArray();
      $configuredTypes = array_keys($appConfig->get('databases', []));
      $databaseTypes = array_intersect($databaseTypes, $configuredTypes);
      $databaseTypeChoices = array_values($databaseTypes);

      $databaseType = select('Select the type of the database', $databaseTypeChoices);
    }

    $databaseType = DatabaseType::tryFrom($databaseType);

    if (! $databaseType ) {
      $output->writeln("<error>Invalid database type</error>\n");
      return Command::FAILURE;
    }

    if ($input->getOption(DatabaseType::MYSQL->value)) {
      $databaseType = DatabaseType::MYSQL;
    } elseif ($input->getOption(DatabaseType::POSTGRESQL->value)) {
      $databaseType = DatabaseType::POSTGRESQL;
    } elseif ($input->getOption(DatabaseType::SQLITE->value)) {
      $databaseType = DatabaseType::SQLITE;
    }

    if (! $dbName ) {
      /** @var array<int|string, string>|Collection<int|string, string> $databaseChoices */
      $databaseChoices = array_keys($appConfig->get("databases.$databaseType->value", []));
      $dbName = select("<info>?</info> Which <question>$databaseType->value</question> database do you want to run the migrations on? ", $databaseChoices);
    }

    if (! $this->isValidDbName($dbName, $databaseType->value, $input, $output) ) {
      $output->writeln("<error>Invalid database name</error>\n");
      return Command::FAILURE;
    }

    # Check if migrations directory exists
    $migrationsDirectory = Path::join($workingDirectory, 'migrations', $databaseType->value, $dbName);

    if (! file_exists($migrationsDirectory) ) {
      $output->writeln("<error>Migrations directory does not exist</error>\n");
      return Command::FAILURE;
    }

    # Check if the database exists
    /** @var MigratorInterface $migrator */
    $migrator = match($databaseType) {
      DatabaseType::MYSQL => new MySQLDatabaseMigrator($dbName, $input, $output),
      DatabaseType::POSTGRESQL => new PostgreSQLDatabase($dbName, $input, $output),
      DatabaseType::SQLITE => new SQLiteDatabase($dbName, $input, $output),
    };

    $numberOfRollbacks = $input->getOption('steps');

    if (! is_numeric($numberOfRollbacks) ) {
      $numberOfRollbacks = null;
    } else {
      $numberOfRollbacks = (int) $numberOfRollbacks;
    }

    if ($numberOfRollbacks < 0) {
      $numberOfRollbacks = null;
    }

    $numberOfSuccessfulRollbacks = $migrator->down($numberOfRollbacks ?? null);

    if (false === $numberOfSuccessfulRollbacks) {
      $output->writeln("<error>Failed to roll back the migrations</error>\n");
      return Command::FAILURE;
    }

    if ($numberOfSuccessfulRollbacks === 0)
    {
      return Command::SUCCESS;
    }

    $output->writeln(" <info>Successfully rolled back $numberOfSuccessfulRollbacks migrations</info>\n");
    return Command::SUCCESS;
  }

}