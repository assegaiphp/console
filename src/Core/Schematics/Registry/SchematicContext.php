<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Assegai\Console\Util\Config\ComposerConfig;
use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SchematicContext
{
  private string $requestedName;
  private string $baseName;
  private string $subdirectory;
  private string $workspace;
  private string $baseNamespace;
  private string $sourceRoot;
  private bool $flat;

  public function __construct(
    public readonly InputInterface $input,
    public readonly OutputInterface $output,
    public readonly SchematicDefinition $definition,
    string $directory,
    ?string $requestedName = null,
  )
  {
    $this->workspace = Path::normalize($directory);
    $this->requestedName = trim(str_replace('\\', '/', (string) ($requestedName ?? '')), '/');
    $this->baseName = $this->requestedName === '' ? '' : basename($this->requestedName);

    [$this->baseNamespace, $this->sourceRoot] = $this->resolveWorkspaceDefaults();
    $this->subdirectory = $this->resolveSubdirectory();
    $this->flat = (bool) $this->getOption('flat', false);
  }

  public function getWorkspace(): string
  {
    return $this->workspace;
  }

  public function getRequestedName(): string
  {
    return $this->requestedName;
  }

  public function getBaseName(): string
  {
    return $this->baseName;
  }

  public function getSubdirectory(): string
  {
    return $this->subdirectory;
  }

  public function getBaseNamespace(): string
  {
    return $this->baseNamespace;
  }

  public function getSourceRoot(): string
  {
    return $this->sourceRoot;
  }

  public function isFlat(): bool
  {
    return $this->flat;
  }

  public function getArgument(string $name, mixed $default = null): mixed
  {
    if (! $this->input->hasArgument($name)) {
      return $default;
    }

    $value = $this->input->getArgument($name);

    return $value === null ? $default : $value;
  }

  public function getOption(string $name, mixed $default = null): mixed
  {
    if (! $this->input->hasOption($name)) {
      return $default;
    }

    $value = $this->input->getOption($name);

    return $value === null ? $default : $value;
  }

  /**
   * @return array<string, mixed>
   */
  public function getArgumentValues(): array
  {
    $values = [];

    foreach ($this->definition->arguments as $argument) {
      $values[$argument->name] = $this->getArgument($argument->name, $argument->default);
    }

    return $values;
  }

  /**
   * @return array<string, mixed>
   */
  public function getOptionValues(): array
  {
    $values = [];

    foreach ($this->definition->options as $option) {
      $values[$option->name] = $this->getOption($option->name, $option->default);
    }

    return $values;
  }

  /**
   * @return array<string, string>
   */
  public function getTemplateVariables(): array
  {
    return SchematicTemplateVariables::build(
      $this->getEffectiveRequestedName(),
      $this->baseNamespace,
      $this->sourceRoot,
      $this->getArgumentValues(),
      $this->getOptionValues(),
    );
  }

  /**
   * @return array{0: string, 1: string}
   */
  private function resolveWorkspaceDefaults(): array
  {
    $baseNamespace = DEFAULT_NAMESPACE;
    $sourceRoot = 'src';

    $projectConfig = new ProjectConfig($this->input, $this->output, $this->workspace);

    if ($projectConfig->load() === Command::SUCCESS) {
      $loadedSourceRoot = $projectConfig->get('sourceRoot', $sourceRoot);

      if (is_string($loadedSourceRoot) && $loadedSourceRoot !== '') {
        $sourceRoot = trim($loadedSourceRoot, '/');
      }
    }

    $composerConfig = new ComposerConfig($this->input, $this->output, $this->workspace);

    if ($composerConfig->load() !== Command::SUCCESS) {
      return [$baseNamespace, $sourceRoot];
    }

    $namespaces = $composerConfig->get('autoload.psr-4', []);

    if (! is_array($namespaces)) {
      return [$baseNamespace, $sourceRoot];
    }

    foreach ($namespaces as $namespace => $path) {
      if ($path === $sourceRoot . '/' || $path === $sourceRoot) {
        $baseNamespace = rtrim((string) $namespace, '\\');
        break;
      }
    }

    return [$baseNamespace, $sourceRoot];
  }

  private function resolveSubdirectory(): string
  {
    $pathOption = trim(str_replace('\\', '/', (string) $this->getOption('path', '')), '/');

    if ($pathOption !== '') {
      return $this->normalizeOutputPath($pathOption);
    }

    $subdirectory = dirname($this->requestedName);

    if ($subdirectory === '.' || $subdirectory === DIRECTORY_SEPARATOR) {
      return '';
    }

    return trim(str_replace('\\', '/', $subdirectory), '/');
  }

  private function normalizeOutputPath(string $path): string
  {
    $path = trim(str_replace('\\', '/', $path), '/');

    if ($path === '' || $path === '.') {
      return '';
    }

    $sourceRoot = trim(str_replace('\\', '/', $this->sourceRoot), '/');

    if ($path === $sourceRoot) {
      return '';
    }

    if (str_starts_with($path, $sourceRoot . '/')) {
      return substr($path, strlen($sourceRoot) + 1);
    }

    return $path;
  }

  private function getEffectiveRequestedName(): string
  {
    if ($this->baseName === '') {
      return '';
    }

    $segments = [];

    if ($this->subdirectory !== '') {
      $segments[] = $this->subdirectory;
    }

    $segments[] = $this->baseName;

    return implode('/', $segments);
  }
}
