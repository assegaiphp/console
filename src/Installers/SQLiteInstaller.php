<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;

class SQLiteInstaller extends AbstractInstaller
{

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $this->output->writeln("Let's configure an SQLite connection!");

    $connectionOptions = [
      'on-disk' => 'on-disk',
      'in-memory' => 'in-memory',
      'in-memory (persistent)' => 'in-memory (persistent)',
    ];

    $defaultDatabaseName = $this->getSuggestedDatabaseName();
    $dbName = $this->prompts->text('Database name', $defaultDatabaseName);

    $defaultPath = $this->getSuggestedSQLitePath((string) $dbName);
    $path = match ((string) $this->prompts->select('Connection type', $connectionOptions, 'on-disk')) {
      'in-memory' => ':memory:',
      'in-memory (persistent)' => 'file::memory:?cache=shared',
      default => $this->prompts->text('Path', $defaultPath)
    };

    $newDatabaseConfig = [
      'databases' => [
        'sqlite' => [
          $dbName => [
            'path' => $path
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
