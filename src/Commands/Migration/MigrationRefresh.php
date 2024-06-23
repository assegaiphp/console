<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'migration:refresh',
    description: 'Refresh the migrations',
    aliases: ['m:refresh', 'migrate:fresh']
)]
class MigrationRefresh extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this
      ->setHelp('This command refreshes the migrations. It rolls back the migrations and then runs them again.')
      ->addArgument('database', InputArgument::REQUIRED, 'The database to refresh the migrations on')
      ->addOption('database_type', 'dt', InputArgument::OPTIONAL, 'The type of the database', DatabaseType::MYSQL->value, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use a MySQL database')
      ->addOption(DatabaseType::POSTGRESQL->value, null,  InputOption::VALUE_NONE, 'Use a PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'User an SQLite database');;
  }

  /**
   * @inheritDoc
   * @throws Throwable
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $input = new ArrayInput([
      'command' => 'migration:redo',
      'database' => $input->getArgument('database'),
      '--database_type' => $input->getOption('database_type'),
      '--migrations' => -1,
      '--' . DatabaseType::MYSQL->value => $input->getOption(DatabaseType::MYSQL->value),
      '--' . DatabaseType::POSTGRESQL->value => $input->getOption(DatabaseType::POSTGRESQL->value),
      '--' . DatabaseType::SQLITE->value => $input->getOption(DatabaseType::SQLITE->value),
    ]);

    return $this->getApplication()?->doRun($input, $output) ?? Command::FAILURE;
  }
}