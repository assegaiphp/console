<?php

namespace Assegai\Console\Core\Packages;

use RuntimeException;
use Symfony\Component\Console\Command\Command;

class InstalledPackageExtension
{
  /**
   * @param string[] $aliases
   * @param string[] $commandClasses
   */
  public function __construct(
    public readonly string $packageName,
    public readonly string $packageRoot,
    public readonly array $aliases = [],
    public readonly array $commandClasses = [],
    public readonly ?string $installerClass = null,
  )
  {
  }

  public function matches(string $nameOrAlias): bool
  {
    if ($this->packageName === $nameOrAlias) {
      return true;
    }

    return in_array($nameOrAlias, $this->aliases, true);
  }

  /**
   * @return Command[]
   */
  public function instantiateCommands(): array
  {
    $commands = [];

    foreach ($this->commandClasses as $commandClass) {
      if (!class_exists($commandClass)) {
        throw new RuntimeException(sprintf(
          'Command class "%s" declared by package "%s" was not found.',
          $commandClass,
          $this->packageName,
        ));
      }

      $command = new $commandClass();

      if (!$command instanceof Command) {
        throw new RuntimeException(sprintf(
          'Command class "%s" declared by package "%s" must extend %s.',
          $commandClass,
          $this->packageName,
          Command::class,
        ));
      }

      $commands[] = $command;
    }

    return $commands;
  }

  public function createInstaller(): ?PackageInstallerInterface
  {
    if ($this->installerClass === null) {
      return null;
    }

    if (!class_exists($this->installerClass)) {
      throw new RuntimeException(sprintf(
        'Installer class "%s" declared by package "%s" was not found.',
        $this->installerClass,
        $this->packageName,
      ));
    }

    $installer = new $this->installerClass();

    if (!$installer instanceof PackageInstallerInterface) {
      throw new RuntimeException(sprintf(
        'Installer class "%s" declared by package "%s" must implement %s.',
        $this->installerClass,
        $this->packageName,
        PackageInstallerInterface::class,
      ));
    }

    return $installer;
  }
}
