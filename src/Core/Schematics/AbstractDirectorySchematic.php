<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\ConfigurableInterface;
use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
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
   * The namespace of the class
   *
   * @var string
   */
  protected string $namespace = 'Assegai\\App';
  /**
   * The namespace suffix of the class
   *
   * @var string
   */
  protected string $namespaceSuffix = '';
  /**
   * The name of the directory
   *
   * @var string
   */
  protected string $directoryName = '';

  /**
   * The structure of the directory
   *
   * @var array<string, string|array> $structure
   */
  protected array $structure = [];
  /**
   * The output of the directory
   *
   * @var array<string, string> $outputDirectory
   */
  protected array $outputDirectory = [];

  /**
   * AbstractDirectorySchematic constructor.
   *
   * @param InputInterface $input The input interface
   * @param OutputInterface $output The output interface
   * @param string $name The name of the schematic
   * @param string $path The path to the directory
   */
  public final function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path,
    protected string $prefix = '',
    protected string $suffix = '',
  )
  {
    $this->directoryName = (new Text($this->name))->pascalCase();
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

    if (! $this->writeFiles($this->outputDirectory) )
    {
      $this->output->writeln(sprintf('<error>Failed to write output for %s</error>', $this->path), OutputInterface::VERBOSITY_VERBOSE);
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

    // Create the root directory
    if (! file_exists($this->directoryName) )
    {
      if (false === mkdir($this->directoryName) )
      {
        $this->output->writeln("<error>Failed creating directory $this->directoryName</error>");
        return false;
      }
    }

    // Walk through the structure and create the subdirectories and files
    foreach ($this->structure as $name => $content)
    {
      // Foreach key value pair in the structure array

      // If the value is an array, create a directory with the key name

      // If the value is a string, create a file with the key name and the value as the content

    }

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

  /**
   * Get the root directory path
   *
   * @return string Returns the root directory path
   */
  private function getRootDirectoryPath(): string
  {
    return Path::join($this->path, $this->directoryName);
  }

  /**
   * Write the output of the directory
   *
   * @param array $directory The directory to write
   * @return bool Returns true if the output was written successfully, false otherwise
   */
  private function writeFiles(array $directory): bool
  {
    // TODO: Implement the writeOutput method

    return true;
  }
}