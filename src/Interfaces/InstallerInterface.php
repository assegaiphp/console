<?php

namespace Assegai\Console\Interfaces;

use RuntimeException;

/**
 * Interface InstallerInterface. For classes that install and uninstall.
 */
interface InstallerInterface
{
  /**
   * Executes the installation.
   *
   * @return int The exit code.
   * @throws RuntimeException
   */
  public function install(): int;

  /**
   * Undo the installation.
   *
   * @return int The exit code.
   */
  public function uninstall(): int;
}