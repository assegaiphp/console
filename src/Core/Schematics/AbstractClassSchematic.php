<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Formatting\InlineAttributePropertiesFormatter;
use Assegai\Console\Core\Formatting\StackedAttributePropertiesFormatter;
use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Enumerations\ClassTemplate;
use Assegai\Console\Util\Config\ComposerConfig;
use Assegai\Console\Util\Inspector;
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
   * For the AppModule.php file update.
   *
   * @return array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} The array of statements for the AppModule.php file
   */
  public function getModuleUpdates(): array
  {
    return [
      'use' => [],
      'declare' => [],
      'provide' => [],
      'control' => [],
      'import' => [],
      'export' => [],
      'config' => [],
    ];
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

    foreach ($this->regex as $regex)
    {
      extract($regex);
      $content = preg_replace($pattern, $replacement, $content ?? '');
    }

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

    if (file_exists($this->getFilePath()) )
    {
      $this->output->writeln("<error>File already exists: {$this->getRelativeFilename()}</error>");
      return Command::FAILURE;
    }

    if (false === touch($this->getFilePath()) )
    {
      $this->output->writeln("<error>Failed to create the file: $this->path</error>");
      return Command::FAILURE;
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

    $this->output->writeln("<info>CREATE</info> {$this->getRelativeFilename()} ($bytes bytes)");

    if ($this->inspector->isValidWorkspace(getcwd() ?: ''))
    {
      if ($localModuleFilename = $this->getLocalModuleFilename())
      {
        if (($status = $this->updateLocalModule($localModuleFilename, $this->getModuleUpdates()) ) !== Command::SUCCESS)
        {
          return $status;
        }
      }
      else
      {
        if (($status = $this->updateAppModule($this->getModuleUpdates()) ) !== Command::SUCCESS)
        {
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
          $this->namespace .= '\\' . $this->namespaceSuffix;
        }
        break;
      }
    }
  }

  /**
   * Get the file path.
   *
   * @return string The file path
   */
  protected function getFilePath(): string
  {
    return Path::join($this->path, $this->getRelativeFilename());
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

  /**
   * Get the relative filename.
   *
   * @return string The relative filename
   */
  protected function getRelativeFilename(): string
  {
    $tail = '';

    if ($this->inspector->isValidWorkspace(getcwd() ?: ''))
    {
      $tail = 'src';
    }

    if (! $this->isFlat )
    {
      $tail = Path::join($tail, $this->properName);
    }

    return Path::join($tail, $this->getFileName());
  }

  /**
   * Get the relative local module filename.
   *
   * @return string The relative local module filename
   */
  protected function getRelativeLocalModuleFilePath(string $localModuleFilename): string
  {
    return Path::join(dirname($this->getRelativeFilename()), $localModuleFilename);
  }

  /**
   * Update the AppModule.php file.
   *
   * @param array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} $props
   * @return int The status of the update.
   */
  protected function updateAppModule(
    array $props = [
      'use' => [],
      'declare' => [],
      'provide' => [],
      'control' => [],
      'import' => [],
      'export' => [],
      'config' => [],
    ]
  ): int
  {
    // TODO: Implement updateAppModule() method.
    $filename = Path::join('src', 'AppModule.php');
    $filePath = Path::join(getcwd() ?: '', $filename);

    if (! file_exists($filePath) )
    {
      $this->output->writeln("<error>File does not exist: $filename</error>");
      return Command::FAILURE;
    }
    $content = file_get_contents($filePath);

    if (! $content)
    {
      $this->output->writeln("<error>Could not read $filename</error>");
      return Command::FAILURE;
    }

    $content = $this->getUpdatedAppModuleContent($content, $props);

    $bytes = file_put_contents($filePath, $content);
    if (false === $bytes)
    {
      $this->output->writeln("<error>Could not write to $filename</error>");
      return Command::FAILURE;
    }

    $this->output->writeln("<fg=bright-blue>UPDATE</> src/AppModule.php ($bytes bytes)");
    return Command::SUCCESS;
  }

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

  /**
   * Get the updated AppModule.php content.
   *
   * @param string $content The content of the AppModule.php file
   * @param array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} $props The properties for the update
   * @return string The updated content of the AppModule.php file
   */
  protected function getUpdatedAppModuleContent(string $content, array $props): string
  {
    $output = $content;

    foreach ($props as $prop => $values)
    {
      if ($prop === 'use')
      {
        // TODO: Replace the use statements

        continue;
      }

      $matches = [];
      $pattern = "/$prop: \[([\w:,\s]*)]/";
      $oldValues = [];
      if (preg_match($pattern, $output ?? '', $matches))
      {
        $oldValues = explode(',', $matches[1] ?? '');
      }

      $newValues = [...$oldValues, ...$values];
      if ((count($values) + count($oldValues)) > 3)
      {
        $replacements = "\n" . implode(",\n", array_map(fn($value) => "    $value", $newValues)) . "\n  ";
      }
      else
      {
        $replacements = implode(', ', $newValues);
      }

      $output = preg_replace($pattern, "$prop: [$replacements]", $output ?? '');
    }

    return $output ?? '';
  }

  /**
   * Retrieve the local module filename if it exists.
   *
   * @return false|string The local module filename, or false if not found
   */
  private function getLocalModuleFilename(): false|string
  {
    $workingDirectory = dirname($this->getFilePath());
    $localFiles = scandir($workingDirectory);

    if (false === $localFiles)
    {
      $this->output->writeln("<error>Failed to scan the directory: $workingDirectory</error>");
      return false;
    }

    foreach ($localFiles as $file)
    {
      if (str_ends_with($file, 'Module.php'))
      {
        return $file;
      }
    }

    return false;
  }

  /**
   * Update the local module file.
   *
   * @param string $localModuleFilename The local module filename
   * @param array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} $props
   * @return int The status of the update
   */
  protected function updateLocalModule(
    string $localModuleFilename,
    array $props
  ): int
  {
    // TODO: Implement updateLocalModule() method.

    $relativeLocalModuleFilename = $this->getRelativeLocalModuleFilePath($localModuleFilename);
    $modulePropertyNameMap = [
      'use' => 'use',
      'declare' => 'declarations',
      'provide' => 'providers',
      'control' => 'controllers',
      'import' => 'imports',
      'export' => 'exports',
    ];
    $moduleFileContent = file_get_contents($relativeLocalModuleFilename) ?: '';
    $originalBytes = strlen($moduleFileContent);

    $bytes = 0;
    foreach ($props as $prop => $values)
    {
      $propertyName = $modulePropertyNameMap[$prop] ?? '';
      if ($prop === 'use')
      {
        continue;
      }

      if (! $propertyName)
      {
        continue;
      }

      $formatter = new InlineAttributePropertiesFormatter($propertyName);
      $oldValues = $formatter->extractValues($moduleFileContent ?? '');

      if (count($oldValues) + count($values) > 3)
      {
        $formatter = new StackedAttributePropertiesFormatter($propertyName);
      }

      $formatter->addValues($values);
      $moduleFileContent = preg_replace($formatter->getPattern(), $formatter->getFormatted($moduleFileContent ?? ''), $moduleFileContent ?? '');
    }

    $bytesToAdd = file_put_contents($relativeLocalModuleFilename, $moduleFileContent);
    if (false === $bytesToAdd)
    {
      $this->output->writeln("<error>Failed to write to the file: $relativeLocalModuleFilename</error>");
      return Command::FAILURE;
    }
    $bytes += $bytesToAdd;

    $this->output->writeln("<fg=bright-blue>UPDATE</> $relativeLocalModuleFilename ($bytes bytes)");
    return Command::SUCCESS;
  }
}