<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Traits\SchematicModuleManagementTrait;
use Assegai\Console\Core\Schematics\Traits\SchematicPathIntrospectionTrait;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractFileSchematic. This class represents a file schematic.
 */
abstract class AbstractFileSchematic implements SchematicInterface
{
  use SchematicPathIntrospectionTrait;
  use SchematicModuleManagementTrait;

  /**
   * @var string $properName The proper name
   */
  protected string $properName = '';
  /**
   * @var Inspector $inspector The inspector
   */
  protected Inspector $inspector;
  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   * @param string $name
   * @param string $path
   * @param string $subdirectory
   * @param string $prefix
   * @param string $suffix
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path,
    protected string $subdirectory = '',
    protected string $prefix = '',
    protected string $suffix = '',
  )
  {
    $this->properName = (new Text($this->name))->pascalCase();
    $this->inspector = new Inspector($this->input, $this->output);
    $this->configure();
  }

  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    // Do nothing
  }

  /**
   * @inheritDoc
   */
  public function build(): int
  {
    $content = $this->getContent();

    # Create the directory recursively if it doesn't exist
    $dir = dirname($this->getFilePath());
    if (false === is_dir($dir) ) {
      if (false === mkdir($dir, 0755, true) ) {
        $this->output->writeln("<error>Failed to create the directory: $dir</error>");
        return Command::FAILURE;
      }
    }

    if (file_exists($this->getFilePath()) ) {
      $this->output->writeln("<error>File already exists: {$this->getRelativeFilename()}</error>");
      return Command::FAILURE;
    }

    if (false === touch($this->getFilePath()) ) {
      $this->output->writeln("<error>Failed to create the file: $this->path</error>");
      return Command::FAILURE;
    }

    # Write to the file
    if (! is_writable($this->getFilePath()) ) {
      $this->output->writeln("<error>File is not writable: {$this->getFileName()}</error>");
      return Command::FAILURE;
    }

    if (! is_file($this->getFilePath()) ) {
      $this->output->writeln("<error>File does not exist: {$this->getFileName()}</error>");
      return Command::FAILURE;
    }

    $bytes = file_put_contents($this->getFilePath(), $content);

    if (false === $bytes) {
      $this->output->writeln("<error>Failed to write to the file: {$this->getFileName()}</error>");
      return Command::FAILURE;
    }

    $this->output->writeln("<info>CREATE</info> {$this->getRelativeFilename()} ($bytes bytes)");

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function tearDown(): int
  {
    if (false === unlink($this->path) ) {
      $this->output->writeln("<error>Failed to delete the file: $this->path</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function finalizeBuild(): int
  {
    // Override this method to perform any necessary operations after the build
    return Command::SUCCESS;
  }

  /**
   * Returns the content of the file.
   *
   * @return string
   */
  abstract protected function getContent(): string;
}