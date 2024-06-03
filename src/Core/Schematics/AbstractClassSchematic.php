<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Util\Config\ComposerConfig;
use Assegai\Console\Util\Path;
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
  protected string $className = '';

  /**
   * AbstractClassSchematic constructor.
   *
   * @param InputInterface $input The input interface
   * @param OutputInterface $output The output interface
   * @param string $name The name of the schematic
   * @param string $path The path to the file
   * @param string $prefix The prefix of the class name
   * @param string $suffix The suffix of the class name
   * @param array $imports The imports of the class
   * @param array $attributes The attributes of the class
   * @param array $properties The properties of the class
   * @param string $constructor The constructor of the class
   * @param array $methods The methods of the class
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path,
    protected string $prefix = '',
    protected string $suffix = '',
    protected array $imports = [],
    protected array $attributes = [],
    protected array $properties = [],
    protected string $constructor = '',
    protected array $methods = [],
  )
  {
    $this->className = (new Text($this->name))->pascalCase();
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

    $content = preg_replace('/class\s(.*)\n\{(\s*)}/', "class $1\n{}", $content);
    $content = preg_replace('/namespace (.*;)(\n*)(.+)/', "namespace $1\n\n$3", $content);

    # Create the directory recursively if it doesn't exist
    $dir = dirname($this->getFilePath());
    if (false === is_dir($dir) )
    {
      if (false === mkdir($dir, 0755, true) )
      {
        $this->output->writeln("<error>Failed to create the directory: $dir</error>");
        return Command::FAILURE;
      }
    }

    # Create the file if it doesn't exist
    if (false === file_exists($this->getFilePath()) )
    {
      if (false === touch($this->getFilePath()) )
      {
        $this->output->writeln("<error>Failed to create the file: $this->path</error>");
        return Command::FAILURE;
      }
    }

    # Write to the file
    if (! is_writable($this->getFilePath()) )
    {
      $this->output->writeln("<error>File is not writable: {$this->getFileName()}</error>");
      return Command::FAILURE;
    }

    if (! is_file($this->getFilePath()) )
    {
      $this->output->writeln("<error>File does not exist: {$this->getFileName()}</error>");
      return Command::FAILURE;
    }

    $bytes = file_put_contents($this->getFilePath(), $content);

    if (false === $bytes)
    {
      $this->output->writeln("<error>Failed to write to the file: {$this->getFileName()}</error>");
      return Command::FAILURE;
    }

    $this->output->writeln("<info>CREATED</info> {$this->getFileName()} ($bytes bytes)");
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
  public function generateDeclaredImports(): string
  {
    $render = '';

    foreach ($this->imports as $import)
    {
      $render .= "use $import;\n";
    }

    return $render;
  }

  /**
   * Get the class attributes. This is the attributes that annotate the class.
   *
   * @return string The class attributes
   */
  public function getClassAttributes(): string
  {
    $render = '';

    if ($this->attributes)
    {
      $render .= "#[";
      $separator = ', ';

      if (count($this->attributes) > 3)
      {
        $render .= "\n";
        $separator = ",\n";
      }

      foreach ($this->attributes as $attribute)
      {
        $render .= $attribute . $separator;
      }

      $render = rtrim($render, $separator);
      $render .= "]\n";
    }

    return $render;
  }

  /**
   * Generate the properties of the class. This is the properties that the class has.
   *
   * @return string The properties of the class
   */
  public function generateProperties(): string
  {
    $render = '';

    foreach ($this->properties as $property)
    {
      $render .= "  $property;\n";
    }

    if (! empty($render) )
    {
      $render .= "\n";
    }

    return $render;
  }

  /**
   * Generate the constructor of the class.
   *
   * @return string The constructor of the class
   */
  public function generateConstructor(): string
  {
    $render = '';

    $lines = explode("\n", $this->constructor);
    foreach ($lines as $line)
    {
      if (empty($line))
      {
        continue;
      }
      $render .= "  $line;\n";
    }

    if (! empty($render) )
    {
      $render .= "\n";
    }

    return $render;
  }

  /**
   * Generate the methods of the class. This is the methods that the class has.
   *
   * @return string The methods of the class
   */
  public function generateMethods(): string
  {
    $render = '';

    foreach ($this->methods as $method)
    {
      $lines = explode("\n", $method);
      foreach ($lines as $line)
      {
        $render .= "  $line;\n";
      }
    }

    if (! empty($render) )
    {
      $render .= "\n";
    }

    return $render;
  }

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

  /**
   * Get the file path.
   *
   * @return string The file path
   */
  protected function getFilePath(): string
  {
    return Path::join($this->path, $this->getFileName());
  }

  /**
   * Get the filename.
   *
   * @return string The filename
   */
  protected function getFileName(): string
  {
    return $this->getClassName() . '.php';
  }
}