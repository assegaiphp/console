<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;

/**
 * Class MySQLInstaller
 *
 * @package Assegai\Console\Installers
 */
class MySQLInstaller extends AbstractInstaller
{
  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $this->output->writeln("Let's configure a MySQL connection!");

    $defaultDatabaseName = $this->getSuggestedDatabaseName();
    $dbName = $this->prompts->text('Database name', $defaultDatabaseName);

    $defaultHost = DEFAULT_MYSQL_HOST;
    $dbHost = $this->prompts->text('Host', $defaultHost);

    $defaultUser = DEFAULT_MYSQL_USER;
    $dbUser = $this->prompts->text('User', $defaultUser);

    $dbPassword = $this->prompts->password('Password');

    $defaultPort = DEFAULT_MYSQL_PORT;
    $dbPort = $this->prompts->text('Port', (string) $defaultPort);

    $newDatabaseConfig = [
      'databases' => [
        'mysql' => [
          $dbName => [
            'host' => $dbHost ?? DEFAULT_MYSQL_HOST,
            'user' => $dbUser ?? DEFAULT_MYSQL_USER,
            'password' => $dbPassword ?? '',
            'port' => $dbPort ?? DEFAULT_MYSQL_PORT,
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
