<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Util\Config\ProjectConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class SQLiteInstaller extends AbstractInstaller
{

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $this->output->writeln("Let's configure an SQLite connection!");

    $connectionOptions = ['on-disk', 'in-memory', 'in-memory (persistent)'];

    $connectionTypeQuestion = new ChoiceQuestion("<info>?</info> Connection type: ", $connectionOptions, 0);

    $dbNameQuestion = new Question("<info>?</info> Database name: ");
    $dbName = $this->questionHelper->ask($this->input, $this->output, $dbNameQuestion);

    $path = 'sqlite:' . match ($connectionType = $this->questionHelper->ask($this->input, $this->output, $connectionTypeQuestion)) {
      'in-memory' => '',
      'in-memory (persistent)' => ':memory:',
      default => $this
                  ->questionHelper
                  ->ask($this->input, $this->output, new Question("<info>?</info> Path: ", DEFAULT_SQLITE_PATH))
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

    return Command::SUCCESS;
  }
}