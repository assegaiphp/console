<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;

class PostgreSQLInstaller extends AbstractInstaller
{

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $this->output->writeln("Let's configure a PostgreSQL connection!");

    $defaultDatabaseName = $this->getSuggestedDatabaseName();
    $dbName = $this->prompts->text('Database name', $defaultDatabaseName);

    $defaultHost = DEFAULT_POSTGRES_HOST;
    $dbHost = $this->prompts->text('Host', $defaultHost);

    $defaultUser = DEFAULT_POSTGRES_USER;
    $dbUser = $this->prompts->text('User', $defaultUser);

    $dbPassword = $this->prompts->password('Password');

    $defaultPort = DEFAULT_POSTGRES_PORT;
    $dbPort = $this->prompts->text('Port', (string) $defaultPort);

    $newDatabaseConfig = [
      'databases' => [
        DatabaseType::POSTGRESQL->value => [
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

    if (false === $projectConfig->updateDatabaseConfig($newDatabaseConfig, $this->projectPath))
    {
      $this->output->writeln("<error>Failed to update workspace config</error>");
      return Command::FAILURE;
    }

    $this->configuredDatabaseName = (string) $dbName;

    return Command::SUCCESS;
  }
}
