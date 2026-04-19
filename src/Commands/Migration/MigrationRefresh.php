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
      ->addArgument(ParameterKey::DB_NAME->value, InputArgument::REQUIRED, 'The database to refresh the migrations on')
      ->addOption(ParameterKey::DB_TYPE->value, ParameterKey::DB_TYPE->getShortName(), InputArgument::OPTIONAL, 'The type of the database', DatabaseType::MYSQL->value, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use a MySQL database')
      ->addOption(DatabaseType::MARIADB->value, null, InputOption::VALUE_NONE, 'Use a MariaDB database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use a PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use an SQLite database')
      ->addOption(DatabaseType::MSSQL->value, null, InputOption::VALUE_NONE, 'Use an MSSQL database');
  }

  /**
   * @inheritDoc
   * @throws Throwable
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $databaseType = get_datasource_type($input, $output);
    $databaseNameParamKey = ParameterKey::DB_NAME->value;
    $databaseTypeParamKey = ParameterKey::DB_TYPE->value;

    $redoInput = new ArrayInput([
      'command' => 'migration:redo',
      $databaseNameParamKey => $input->getArgument($databaseNameParamKey),
      "--$databaseTypeParamKey" => $databaseType,
      '--steps' => -1,
      '--' . DatabaseType::MYSQL->value => $input->getOption(DatabaseType::MYSQL->value),
      '--' . DatabaseType::MARIADB->value => $input->getOption(DatabaseType::MARIADB->value),
      '--' . DatabaseType::POSTGRESQL->value => $input->getOption(DatabaseType::POSTGRESQL->value),
      '--' . DatabaseType::SQLITE->value => $input->getOption(DatabaseType::SQLITE->value),
      '--' . DatabaseType::MSSQL->value => $input->getOption(DatabaseType::MSSQL->value),
    ]);

    return $this->getApplication()?->doRun($redoInput, $output) ?? Command::FAILURE;
  }
}
