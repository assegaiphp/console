<?php

namespace Assegai\Console\Util;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Inspector class. This class is responsible for inspecting the workspace directory.
 *
 * @package Assegai\Console\Util
 */
class Inspector
{
  protected InstalledVersions $installedVersions;

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
    $this->installedVersions = new InstalledVersions();
  }

  /**
   * Check if the given package is installed globally.
   *
   * @param string $packageName The package name.
   * @return bool
   */
  public function packageIsInstalledGlobally(string $packageName): bool
  {
    return in_array($packageName, $this->installedVersions->getInstalledPackages());
  }

  /**
   * Check if the given path is a valid project directory.
   *
   * @param string $workspaceDirectory The path to check.
   * @return bool
   */
  public function isValidWorkspace(string $workspaceDirectory): bool
  {
    if (! is_dir($workspaceDirectory) )
    {
      $this->output->writeln("Workspace $workspaceDirectory does not exist.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    // Check if the workspace is empty
    $workspaceFiles = scandir($workspaceDirectory);

    if (false === $workspaceFiles)
    {
      $this->output->writeln("Failed to read workspace $workspaceDirectory.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    if (count($workspaceFiles) === 2)
    {
      $this->output->writeln("Workspace $workspaceDirectory is empty.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    // Check if the workspace has a valid composer.json file
    if (! file_exists(Path::join($workspaceDirectory, 'composer.json')))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have a composer.json file.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    // Check if the workspace has a valid assegai.json file
    if (! file_exists(Path::join($workspaceDirectory, 'assegai.json')))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have an assegai.json file.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    // Check if the workspace has a valid bootstrap file
    $bootstrapFilename = BOOTSTRAP_FILE;
    if (! file_exists(Path::join($workspaceDirectory, $bootstrapFilename)) )
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have a $bootstrapFilename file.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    return true;
  }

  /**
   * Check if the given path is not a valid project directory.
   *
   * @param string $workspaceDirectory The path to check.
   * @return bool
   */
  public function isNotAValidWorkspace(string $workspaceDirectory): bool
  {
    return ! $this->isValidWorkspace($workspaceDirectory);
  }

  /**
   * Get the version of the CLI package.
   *
   * @return string
   */
  public function getCLIVersion(): string
  {
    $packageName = PACKAGE_NAME_CLI;
    return $this->getPackageVersion($packageName, null, true) ?: 'Not installed';
  }

  /**
   * Get the version of the installed framework.
   *
   * @param string|null $workingDirectory The workspace directory.
   * @return string
   */
  public function getInstalledFrameworkVersion(?string $workingDirectory = null): string
  {
    $workingDirectory = $workingDirectory ?? getcwd();
    if (false === $workingDirectory) {
      return 'Not installed';
    }

    $packageName = PACKAGE_NAME_CORE;
    return $this->getPackageVersion($packageName, $workingDirectory) ?: 'Not installed';
  }

  /**
   * Get the version of the given package.
   *
   * @param string $packageName The package name.
   * @param string|null $workspace The workspace directory.
   * @param bool $isGlobal Whether the package is installed globally.
   * @return false|string
   */
  public function getPackageVersion(
    string $packageName,
    ?string $workspace = null,
    bool $isGlobal = false
  ): false|string
  {
    if (! $this->packageIsInstalledGlobally($packageName) ) {
      return false;
    }

    try {
      return $this->installedVersions->getVersion($packageName) ?? false;
    } catch (OutOfBoundsException $e) {
      return $e->getMessage();
    }
  }
}