<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\DBConfig;
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
  aliases: ['migrations:redo']
)]
class MigrationRedo extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this
      ->addArgument('database', InputArgument::REQUIRED, 'The database to redo the migration on')
      ->addOption('migrations', 'm', InputArgument::OPTIONAL, 'The number of migrations to redo', 1)
      ->addOption('database_type', 't', InputArgument::OPTIONAL, 'The type of the database', DatabaseType::MYSQL->value, DatabaseType::toArray())
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
    $databaseType = $input->getOption('database_type');

    if (!DatabaseType::isValid($databaseType)) {
      $output->writeln("<error>Invalid database type $databaseType</error>");
      return Command::FAILURE;
    }

    $numberOfMigrations = $input->getOption('migrations') ?? 1;

    if (!is_numeric($numberOfMigrations))
    {
      $output->writeln("<error>The number of migrations must be a number</error>");
      return Command::FAILURE;
    }

    $database = $input->getArgument('database');

    $downInput = new ArrayInput([
      'command' => 'migration:down',
      'database' => $database,
      '--migrations' => $numberOfMigrations,
      '--database_type' => $databaseType
    ]);
    if (Command::SUCCESS !== $this->getApplication()->doRun($downInput, $output))
    {
      $output->writeln("<error>Failed to undo the migrations</error>");
      return Command::FAILURE;
    }

    $upInput = new ArrayInput([
      'command' => 'migration:up',
      'database' => $database,
      '--migrations' => $numberOfMigrations,
      '--database_type' => $databaseType
    ]);
    if (Command::SUCCESS !== $this->getApplication()->doRun($upInput, $output))
    {
      $output->writeln("<error>Failed to redo the migrations</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}