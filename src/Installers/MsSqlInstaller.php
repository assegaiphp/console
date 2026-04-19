<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;

class MsSqlInstaller extends AbstractInstaller
{
  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $this->output->writeln("Let's configure an MSSQL connection!");

    $defaultDatabaseName = $this->getSuggestedDatabaseName();
    $dbName = $this->prompts->text('Database name', $defaultDatabaseName);

    $dbHost = $this->prompts->text('Host', DEFAULT_MSSQL_HOST);
    $dbUser = $this->prompts->text('User', DEFAULT_MSSQL_USER);
    $dbPassword = $this->prompts->password('Password');
    $dbPort = $this->prompts->text('Port', (string) DEFAULT_MSSQL_PORT);

    $newDatabaseConfig = [
      'databases' => [
        DatabaseType::MSSQL->value => [
          $dbName => [
            'host' => $dbHost,
            'user' => $dbUser,
            'password' => $dbPassword,
            'port' => $dbPort,
          ]
        ]
      ]
    ];

    $projectConfig = new ProjectConfig($this->input, $this->output);

    if (false === $projectConfig->updateDatabaseConfig($newDatabaseConfig, $this->projectPath)) {
      $this->output->writeln("<error>Failed to update workspace config</error>");
      return Command::FAILURE;
    }

    $this->configuredDatabaseName = (string) $dbName;

    return Command::SUCCESS;
  }
}
