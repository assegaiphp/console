<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;

class PostgreSQLInstaller extends AbstractInstaller
{

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $this->output->writeln("Let's configure a PostgreSQL connection!");
    $dbNameQuestion = new Question("<info>?</info> Database name: ");
    $dbName = $this->questionHelper->ask($this->input, $this->output, $dbNameQuestion);

    $defaultHost = DEFAULT_POSTGRES_HOST;
    $dbHostQuestion = new Question("<info>?</info> Host: <fg=gray>($defaultHost)</>: ", $defaultHost);
    $dbHost = $this->questionHelper->ask($this->input, $this->output, $dbHostQuestion);

    $defaultUser = DEFAULT_POSTGRES_USER;
    $dbUserQuestion = new Question("<info>?</info> User: <fg=gray>($defaultUser)</>: ", $defaultUser);
    $dbUser = $this->questionHelper->ask($this->input, $this->output, $dbUserQuestion);

    $dbPasswordQuestion = new Question("<info>?</info> Password: ");
    $dbPasswordQuestion->setHidden(true);
    $dbPassword = $this->questionHelper->ask($this->input, $this->output, $dbPasswordQuestion);

    $defaultPort = DEFAULT_POSTGRES_PORT;
    $dbPortQuestion = new Question("<info>?</info> Port: <fg=gray>($defaultPort)</> ", $defaultPort);
    $dbPort = $this->questionHelper->ask($this->input, $this->output, $dbPortQuestion);

    $newDatabaseConfig = [
      'databases' => [
        'postgresql' => [
          $dbName => [
            'host' => $dbHost ?? DEFAULT_POSTGRES_HOST,
            'user' => $dbUser ?? DEFAULT_POSTGRES_USER,
            'password' => $dbPassword ?? '',
            'port' => $dbPort ?? DEFAULT_POSTGRES_PORT,
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