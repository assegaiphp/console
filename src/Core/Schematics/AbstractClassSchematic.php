<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Util\Config\ComposerConfig;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractClassSchematic implements SchematicInterface
{
  /**
   * The namespace of the class
   *
   * @var string
   */
  protected string $namespace = 'Assegai\\App';

  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path,
    protected string $prefix = '',
    protected string $suffix = ''
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function build(): int
  {
    $this->loadNamespaceFromConfig();

    $content = <<<PHP
<?php

namespace $this->namespace;

{$this->generateDeclaredImports()}

{$this->getClassAttributes()}
class {$this->getClassName()}
{
  {$this->generateProperties()}

  {$this->generateConstructor()}

  {$this->generateMethods()}
}

PHP;

    # Create the directory recursively if it doesn't exist
    $dir = dirname($this->path);
    if (false === is_dir($dir) )
    {
      if (false === mkdir($dir, 0755, true) )
      {
        $this->output->writeln("<error>Failed to create the directory: $dir</error>");
        return Command::FAILURE;
      }
    }

    # Create the file if it doesn't exist
    if (false === file_exists($this->path) )
    {
      if (false === touch($this->path) )
      {
        $this->output->writeln("<error>Failed to create the file: $this->path</error>");
        return Command::FAILURE;
      }
    }

    # Write to the file
    if (false === file_put_contents($this->path, $content) )
    {
      $this->output->writeln("<error>Failed to write to the file: $this->path</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function tearDown(): int
  {
    if (false === unlink($this->path) )
    {
      $this->output->writeln("<error>Failed to delete the file: $this->path</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  public function getClassName(): string
  {
    $prefix = $this->prefix ? $this->prefix . '-' : '';
    $suffix = $this->suffix ? '-' . $this->suffix : '';

    return (new Text($prefix . $this->name . $suffix))->pascalCase();
  }

  /**
   * Generate the declared imports. This is the imports that are declared at the top of the class.
   *
   * @return string The declared imports
   */
  public abstract function generateDeclaredImports(): string;

  /**
   * Get the class attributes. This is the attributes that annotate the class.
   *
   * @return string The class attributes
   */
  public abstract function getClassAttributes(): string;

  /**
   * Generate the properties of the class. This is the properties that the class has.
   *
   * @return string The properties of the class
   */
  public abstract function generateProperties(): string;

  /**
   * Generate the constructor of the class.
   *
   * @return string The constructor of the class
   */
  public abstract function generateConstructor(): string;

  /**
   * Generate the methods of the class. This is the methods that the class has.
   *
   * @return string The methods of the class
   */
  public abstract function generateMethods(): string;

  /**
   * Load the namespace from the configuration file.
   *
   * @return void
   */
  public function loadNamespaceFromConfig(): void
  {
    $config = new ComposerConfig($this->input, $this->output);
    $config->load();

    $this->namespace = $config->get('namespace') ?? $this->namespace;
  }
}