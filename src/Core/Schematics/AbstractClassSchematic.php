<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Enumerations\ClassTemplate;
use Assegai\Console\Core\Schematics\Traits\SchematicModuleManagementTrait;
use Assegai\Console\Core\Schematics\Traits\SchematicPathIntrospectionTrait;
use Assegai\Console\Util\Config\ComposerConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractClassSchematic. This class represents a class schematic.
 *
 * @package Assegai\Console\Core\Schematics
 */
abstract class AbstractClassSchematic implements SchematicInterface
{
  use SchematicPathIntrospectionTrait;
  use SchematicModuleManagementTrait;

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
   * The proper name of the class. This is the name in PascalCase.
   *
   * @var string
   */
  protected string $properName = '';
  /**
   * @var array<array{pattern: string, replacement: string}>
   */
  protected array $regex = [
    ['pattern' => '/(class|interface|enum|abstract)\s(.*)\n\{(\s*)}/', 'replacement' => "$1 $2\n{}"],
    ['pattern' => '/namespace (.*;)(\n*)(.+)/', 'replacement' => "namespace $1\n\n$3"],
    ['pattern' => '/](\s+)(class|interface|enum|abstract)/', 'replacement' => "]\n$2"],
    ['pattern' => '/{(\n{2,})(\s*)(public|private|protected|function)/', 'replacement' => "\{\n$2$3"],
    ['pattern' => '/(\s+)}\n{2,}}/', 'replacement' => "$1}\n}"],
  ];
  /**
   * @var Inspector $inspector The inspector
   */
  protected Inspector $inspector;

  /**
   * AbstractClassSchematic constructor.
   *
   * @param InputInterface $input The input interface
   * @param OutputInterface $output The output interface
   * @param string $name The name of the schematic
   * @param string $path The path to the file
   * @param string $subdirectory The subdirectory of the class
   * @param string $prefix The prefix of the class name
   * @param string $suffix The suffix of the class name
   * @param string[] $imports The imports of the class
   * @param string[] $attributes The attributes of the class
   * @param string[] $properties The properties of the class
   * @param string $constructor The constructor of the class
   * @param string[] $methods The methods of the class
   * @param bool $isFlat Whether the class is flat
   * @param ClassTemplate $template The template of the class
   * @param string $parent The parent of the class
   * @param string[] $interfaces The interfaces of the class
   */
  public final function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path,
    protected string $subdirectory = '',
    protected string $prefix = '',
    protected string $suffix = '',
    protected array $imports = [],
    protected array $attributes = [],
    protected array $properties = [],
    protected string $constructor = '',
    protected array $methods = [],
    protected bool $isFlat = false,
    protected ClassTemplate $template = ClassTemplate::DEFAULT,
    protected string $parent = '',
    protected array $interfaces = [],
  )
  {
    $this->properName = (new Text($this->name))->pascalCase();
    $this->inspector = new Inspector($this->input, $this->output);
    $this->configure();
  }

  /**
   * Configure the class schematic.
   *
   * @return void
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
    $this->loadNamespaceFromConfig();

    $content = <<<PHP
<?php

namespace $this->namespace;

{$this->generateDeclaredImports()}
{$this->getClassAttributes()}
{$this->template->value} {$this->getClassName()}{$this->getClassAncestors()}
{
  {$this->generateProperties()}
  {$this->generateConstructor()}
  {$this->generateMethods()}
}

PHP;

    foreach ($this->regex as $regex) {
      extract($regex);
      $content = preg_replace($pattern, $replacement, $content ?? '');
    }

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

    if ($this->inspector->isValidWorkspace(getcwd() ?: '')) {
      if ($localModuleFilename = $this->getLocalModuleFilename()) {
        if (($status = $this->updateLocalModule($localModuleFilename, $this->getModuleUpdates()) ) !== Command::SUCCESS) {
          return $status;
        }
      } else {
        if (($status = $this->updateAppModule($this->getModuleUpdates()) ) !== Command::SUCCESS) {
          return $status;
        }
      }
    }

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
        $render .= "$line\n";
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

    $namespaces = $config->get('autoload.psr-4');
    foreach ($namespaces as $namespace => $path)
    {
      if ($path === 'src/')
      {
        $this->namespace = rtrim($namespace, '\\');
        if ($this->namespaceSuffix)
        {
          $this->namespace .= '\\' . ltrim($this->namespaceSuffix, '\\');
        }
        break;
      }
    }
  }

  /**
   * @return string
   */
  protected function getClassAncestors(): string
  {
    $render = '';

    if ($this->parent)
    {
      $render .= " extends $this->parent";
    }

    if ($this->interfaces)
    {
      $render .= " implements " . implode(', ', $this->interfaces);
    }

    return $render;
  }
}