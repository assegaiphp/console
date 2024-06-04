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
    $installCommand = `cd $this->projectPath && composer --ansi require assegaiphp/core && composer install`;

    if (false === $installCommand)
    {
      $this->output->writeln('<error>Failed to install composer dependencies</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}