<?php

namespace Assegai\Console\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Inspector class. This class is responsible for inspecting the workspace directory.
 *
 * @package Assegai\Console\Util
 */
class Inspector
{
  /**
   *
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output
  )
  {
  }

  /**
   * Check if the given path is a valid project directory.
   *
   * @param string $workspaceDirectory The path to check.
   * @return bool
   */
  public function isValidWorkspace(string $workspaceDirectory): bool
  {
    if (! is_dir($workspaceDirectory))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not exist.");
      return false;
    }

    // Check if the workspace is empty
    if (count(scandir($workspaceDirectory)) === 2)
    {
      $this->output->writeln("Workspace $workspaceDirectory is empty.");
      return false;
    }

    // Check if the workspace has a valid composer.json file
    if (! file_exists(Path::join($workspaceDirectory, 'composer.json')))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have a composer.json file.");
      return false;
    }

    // Check if the workspace has a valid assegai.json file
    if (! file_exists(Path::join($workspaceDirectory, 'assegai.json')))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have an assegai.json file.");
      return false;
    }

    // Check if the workspace has a valid assegai-router.php file
    if (! file_exists(Path::join($workspaceDirectory, 'assegai-router.php')) )
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have an assegai-router.php file.");
      return false;
    }

    return true;
  }
}