<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Traits\NamespaceReflectivityTrait;
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
  use NamespaceReflectivityTrait;

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
   * @var array<string, string|array<string, mixed>> $structure
   */
  protected array $structure = [];
  /**
   * The output of the directory
   *
   * @var array<string, string> $outputDirectory
   */
  protected array $outputDirectory = [];
  /**
   * The name text
   *
   * @var Text $nameText
   */
  protected Text $nameText;
  /**
   * The singular text
   *
   * @var Text $singularName
   */
  protected Text $singularName;
  /**
   * @var int The total number of writes
   */
  protected int $totalWrites = 0;

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
    $this->nameText = new Text($this->name);
    $this->singularName = new Text($this->nameText->getSingularForm());
    $this->directoryName = $this->nameText->pascalCase();
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
  public function prepareBuild(): int
  {
    // Override this method to perform any necessary operations before the build
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function build(): int
  {
    $this->loadNamespaceFromConfig();

    $outputStructure = $this->scaffold($this->structure);
    $outputStructure = $this->resolvePathNames($outputStructure);
    $outputStructure = $this->resolveContent($outputStructure);

    $this->totalWrites = 0;
    if (! $this->writeFiles($this->getRootDirectoryPath(), $outputStructure) ) {
      $this->output->writeln(sprintf('<error>Failed to write output for %s</error>', $this->path), OutputInterface::VERBOSITY_VERBOSE);
      return Command::FAILURE;
    }

    if ($this->totalWrites === 0) {
      $this->output->writeln('<comment>Nothing to do!</comment>');
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
   * @inheritDoc
   */
  public function prepareTearDown(): int
  {
    // Override this method to perform any necessary operations before the teardown
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function tearDown(): int
  {
    if (false === unlink($this->path)) {
      $this->output->writeln(sprintf('<error>Failed to delete %s</error>', $this->path));
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function finalizeTearDown(): int
  {
    // Override this method to perform any necessary operations after the teardown
    return Command::SUCCESS;
  }

  /**
   * Scaffold the directory. This method should create the directory if it does not exist as well as any
   * subdirectories and files.
   *
   * @param array<string, array<string, mixed>|string> $structure The structure of the directory
   * @return array<string, array<string, mixed>|string> Returns the structure of the directory if it was scaffolded successfully, false otherwise
   */
  private function scaffold(array $structure): array
  {
    $output = [];

    foreach ($structure as $name => $value) {
      $output[$name] = $value;
    }

    return $output;
  }

  /**
   * Resolves all the directory and file names in the path
   *
   * @param array<string, array<string, mixed>|string> $structure The structure of the directory
   * @return array<string, array<string, mixed>|string> Returns true if the path names were resolved successfully, false otherwise
   */
  private function resolvePathNames(array $structure): array
  {
    $output = [];

    foreach ($structure as $name => $value) {
      $path = str_replace('__NAME__', $this->nameText->pascalCase(), $name);
      $path = str_replace('__SINGULAR_LC__', strtolower($this->singularName->pascalCase()), $path);
      $path = str_replace('__SINGULAR__', $this->singularName->pascalCase(), $path);

      $output[$path] = is_array($value) ? $this->resolvePathNames($value) : $value;
    }

    return $output;
  }

  /**
   * Resolves the content of the directory. This method performs any necessary operations to generate the content of
   * the directory.
   *
   * @param array<string, array<string, mixed>|string> $structure The structure of the directory
   * @return array<string, array<string, mixed>|string> Returns true if the content was resolved successfully, false otherwise
   */
  private function resolveContent(array $structure): array
  {
    $output = [];

    foreach ($structure as $name => $value) {
      $content = $value;
      if (is_string($content)) {
        $content = str_replace(DEFAULT_NAMESPACE, $this->namespace, $content);
        $content = str_replace('__NAME__', $this->nameText->pascalCase(), $content);
        $content = str_replace('__KEBAB__', $this->nameText->kebabCase(), $content);
        $content = str_replace('__CAMEL__', $this->nameText->camelCase(), $content);
        $content = str_replace('__SINGULAR_LC__', strtolower($this->singularName->pascalCase()), $content);
        $content = str_replace('__SINGULAR__', $this->singularName->pascalCase(), $content);
      }

      $output[$name] = is_array($content) ? $this->resolveContent($content) : $content;
    }

    return $output;
  }

  /**
   * Get the root directory path
   *
   * @return string Returns the root directory path
   */
  private function getRootDirectoryPath(): string
  {
    return Path::join($this->path, 'src', $this->directoryName);
  }

  /**
   * Write the output of the directory
   *
   * @param string $workingDirectory The working directory
   * @param array<string, array<string, mixed>|string> $directoryStructure The directory structure
   * @return bool Returns true if the output was written successfully, false otherwise
   */
  private function writeFiles(string $workingDirectory, array $directoryStructure): bool
  {
    if (! file_exists($workingDirectory) ) {
      if (false === mkdir($workingDirectory) ) {
        $this->output->writeln("<error>Failed creating directory $workingDirectory</error>");
        return false;
      }
    }

    foreach ($directoryStructure as $name => $content) {
      $path = Path::join($workingDirectory, $name);
      if (is_array($content)) {
        if (! $this->writeFiles($path, $content)) {
          $this->output->writeln("<error>Failed creating directory $path</error>");
          return false;
        }
      }

      if (is_string($content) && ! file_exists($path) ) {
        $bytes = file_put_contents($path, $content);
        if (false === $bytes) {
          $this->output->writeln("<error>Failed creating file $path</error>");
          return false;
        }
        $this->totalWrites++;

        $bytes = format_bytes($bytes);

        $filename = str_replace(Path::join($this->path, 'src') . DIRECTORY_SEPARATOR, '', $path);
        $this->output->writeln("<info>CREATE</info> $filename ($bytes)");
      }
    }

    return true;
  }
}