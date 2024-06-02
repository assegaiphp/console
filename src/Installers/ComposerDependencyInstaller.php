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
    // TODO: Implement install() method.

    return Command::SUCCESS;
  }
}