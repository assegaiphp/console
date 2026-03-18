<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Installers\AbstractInstaller;
use Symfony\Component\Console\Command\Command;

class ComposerDependencyInstaller extends AbstractInstaller
{

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    $installCommand = shell_exec(
      sprintf(
        'cd %s && composer install --ansi',
        escapeshellarg($this->projectPath)
      )
    );

    if (false === $installCommand)
    {
      $this->output->writeln('<error>Failed to install composer dependencies</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}
