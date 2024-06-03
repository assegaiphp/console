<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;

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
    $dbNameQuestion = new Question("<info>?</info> Database name: ");
    $dbName = $this->questionHelper->ask($this->input, $this->output, $dbNameQuestion);

    $defaultHost = DEFAULT_MYSQL_HOST;
    $dbHostQuestion = new Question("<info>?</info> Host: ($defaultHost) ", $defaultHost);
    $dbHost = $this->questionHelper->ask($this->input, $this->output, $dbHostQuestion);

    $defaultUser = DEFAULT_MYSQL_USER;
    $dbUserQuestion = new Question("<info>?</info> User: ($defaultUser) ", $defaultUser);
    $dbUser = $this->questionHelper->ask($this->input, $this->output, $dbUserQuestion);

    $dbPasswordQuestion = new Question("<info>?</info> Password: ", "");
    $dbPasswordQuestion->setHidden(true);
    $dbPassword = $this->questionHelper->ask($this->input, $this->output, $dbPasswordQuestion);

    $defaultPort = DEFAULT_MYSQL_PORT;
    $dbPortQuestion = new Question("<info>?</info> Port:($defaultPort) ", $defaultPort);
    $dbPort = $this->questionHelper->ask($this->input, $this->output, $dbPortQuestion);

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

    return Command::SUCCESS;
  }
}