<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
  name: 'migration:redo',
  description: 'Redo the last migration',
  aliases: ['m:redo']
)]
class MigrationRedo extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this
      ->addArgument(ParameterKey::DB_NAME->value, InputArgument::REQUIRED, 'The database to redo the migration on')
      ->addOption('steps', 's', InputArgument::OPTIONAL, 'The number of migrations to redo', 1)
      ->addOption(ParameterKey::DB_TYPE->value, ParameterKey::DB_TYPE->getShortName(), InputArgument::OPTIONAL, 'The type of the database', DatabaseType::MYSQL->value, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use a MySQL database')
      ->addOption(DatabaseType::POSTGRESQL->value, null,  InputOption::VALUE_NONE, 'Use a PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'User an SQLite database');
  }

  /**
   * @inheritDoc
   * @throws Throwable
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $databaseType = get_datasource_type($input, $output);

    if (false === $databaseType || !DatabaseType::isValid($databaseType)) {
      $output->writeln("<error>Invalid database type $databaseType</error>");
      return Command::FAILURE;
    }

    $numberOfMigrations = $input->getOption('steps') ?? 1;
    $application = $this->getApplication();

    if (!$application) {
      $output->writeln("<error>Failed to get the application</error>");
      return Command::FAILURE;
    }

    if (!is_numeric($numberOfMigrations)) {
      $output->writeln("<error>The number of migrations must be a number</error>");
      return Command::FAILURE;
    }

    $database = $input->getArgument(ParameterKey::DB_NAME->value);

    $downInput = new ArrayInput([
      'command' => 'migration:down',
      ParameterKey::DB_NAME->value => $database,
      '--steps' => $numberOfMigrations,
      '--database_type' => $databaseType
    ]);

    if (Command::SUCCESS !== $application->doRun($downInput, $output)) {
      $output->writeln("<error>Failed to undo the migrations</error>");
      return Command::FAILURE;
    }

    $upInput = new ArrayInput([
      'command' => 'migration:up',
      ParameterKey::DB_NAME->value => $database,
      '--steps' => $numberOfMigrations,
      '--database_type' => $databaseType
    ]);

    if (Command::SUCCESS !== $application->doRun($upInput, $output)) {
      $output->writeln("<error>Failed to redo the migrations</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}