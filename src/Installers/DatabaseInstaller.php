<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Installers\AbstractInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DatabaseInstaller extends AbstractInstaller
{

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    // TODO: Implement install() method.
    if (! $this->questionHelper->ask($this->input, $this->output, new ConfirmationQuestion('Do you want to install the database? (y/n) ')))
    {
      $this->output->writeln('Skipping database installation...');
      return Command::SUCCESS;
    }

    $this->output->writeln($this->formatter->formatBlock('Installing the database...', 'comment', true));

    if ($missingExtensions = $this->checkForMissingExtensions(['pdo_mysql', 'mysqli']))
    {
      $this->output->writeln($this->formatter->formatBlock('The following extensions are missing: ' . implode(', ', $missingExtensions), 'error', true));
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Check for missing extensions.
   *
   * @param string[] $extensions The extensions to check for.
   *
   * @return string[] The missing extensions.
   */
  private function checkForMissingExtensions(array $extensions): array
  {
    $missingExtensions = [];

    return $missingExtensions;
  }
}