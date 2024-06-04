<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractDirectorySchematic. This class is a base class for all directory schematics.
 *
 * @package Assegai\Console\Core\Schematics
 */
abstract class AbstractDirectorySchematic implements SchematicInterface
{
  /**
   * AbstractDirectorySchematic constructor.
   *
   * @param InputInterface $input The input interface
   * @param OutputInterface $output The output interface
   * @param string $name The name of the schematic
   * @param string $path The path to the directory
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path,
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function build(): int
  {
    if (! $this->scaffold())
    {
      $this->output->writeln(sprintf('<error>Failed to create %s</error>', $this->path), OutputInterface::VERBOSITY_VERBOSE);
      return Command::FAILURE;
    }

    if (! $this->resolvePathNames())
    {
      $this->output->writeln(sprintf('<error>Failed to resolve path names for %s</error>', $this->path), OutputInterface::VERBOSITY_VERBOSE);
      return Command::FAILURE;
    }

    if (! $this->resolveContent())
    {
      $this->output->writeln(sprintf('<error>Failed to resolve content for %s</error>', $this->path), OutputInterface::VERBOSITY_VERBOSE);
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function tearDown(): int
  {
    if (false === unlink($this->path))
    {
      $this->output->writeln(sprintf('<error>Failed to delete %s</error>', $this->path));
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Scaffold the directory. This method should create the directory if it does not exist as well as any
   * subdirectories and files.
   *
   * @return bool Returns true if the directory was scaffolded successfully, false otherwise
   */
  private function scaffold(): bool
  {
    // TODO: Implement the scaffold method

    return true;
  }

  /**
   * Resolves all the directory and file names in the path
   *
   * @return bool Returns true if the path names were resolved successfully, false otherwise
   */
  private function resolvePathNames(): bool
  {
    // TODO: Implement the resolvePathNames method
    return true;
  }

  /**
   * Resolves the content of the directory. This method performs any necessary operations to generate the content of
   * the directory.
   *
   * @return bool Returns true if the content was resolved successfully, false otherwise
   */
  private function resolveContent(): bool
  {
    // TODO: Implement the resolveContent method

    return true;
  }
}