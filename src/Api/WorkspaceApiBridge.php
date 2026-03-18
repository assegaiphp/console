<?php

namespace Assegai\Console\Api;

use Assegai\Console\Util\ComposerManifest;
use Assegai\Console\Util\Path;
use RuntimeException;

class WorkspaceApiBridge
{
  private bool $workspaceAutoloadLoaded = false;

  public function __construct(private readonly string $workspace)
  {
  }

  /**
   * @return array<string, mixed>
   */
  public function generateOpenApiDocument(): array
  {
    $this->loadWorkspaceAutoload();
    $rootModuleClass = $this->resolveRootModuleClass();
    $generator = $this->newCoreInstance(
      'Assegai\\Core\\ApiDocs\\OpenApiGenerator',
      $this->callStaticCoreMethod('Assegai\\Core\\ControllerManager', 'getInstance'),
      $this->callStaticCoreMethod('Assegai\\Core\\ModuleManager', 'getInstance'),
      $this->callStaticCoreMethod('Assegai\\Core\\Http\\Requests\\Request', 'getInstance'),
      $this->newCoreInstance('Assegai\\Core\\Config\\ComposerConfig'),
      $this->newCoreInstance('Assegai\\Core\\Config\\ProjectConfig'),
    );

    $document = $generator->generate($rootModuleClass);

    if (!is_array($document)) {
      throw new RuntimeException('The OpenAPI generator did not return a valid document.');
    }

    return $document;
  }

  /**
   * @param array<string, mixed> $document
   * @return array<string, mixed>
   */
  public function generatePostmanCollection(array $document): array
  {
    $generator = $this->newCoreInstance('Assegai\\Core\\ApiDocs\\PostmanCollectionGenerator');
    $collection = $generator->generate($document);

    if (!is_array($collection)) {
      throw new RuntimeException('The Postman exporter did not return a valid collection.');
    }

    return $collection;
  }

  /**
   * @param array<string, mixed> $document
   */
  public function generateTypeScriptClient(array $document): string
  {
    $generator = $this->newCoreInstance('Assegai\\Core\\ApiDocs\\TypeScriptClientGenerator');
    $client = $generator->generate($document);

    if (!is_string($client) || $client === '') {
      throw new RuntimeException('The TypeScript client generator did not return any content.');
    }

    return $client;
  }

  public function resolveRootModuleClass(): string
  {
    $this->loadWorkspaceAutoload();
    $bootstrapFile = Path::join($this->workspace, BOOTSTRAP_FILE);

    if (is_file($bootstrapFile)) {
      $resolved = $this->resolveRootModuleFromBootstrap($bootstrapFile);

      if ($resolved !== null) {
        return $resolved;
      }
    }

    $resolved = $this->resolveRootModuleFromComposer();

    if ($resolved !== null) {
      return $resolved;
    }

    $resolved = $this->resolveRootModuleFromSource();

    if ($resolved !== null) {
      return $resolved;
    }

    throw new RuntimeException('Unable to determine the application root module.');
  }

  private function loadWorkspaceAutoload(): void
  {
    if ($this->workspaceAutoloadLoaded) {
      return;
    }

    $autoloadFile = Path::join($this->workspace, 'vendor', 'autoload.php');

    if (!is_file($autoloadFile)) {
      throw new RuntimeException('This project is missing vendor/autoload.php. Run composer install first.');
    }

    require_once $autoloadFile;
    $this->workspaceAutoloadLoaded = true;
  }

  private function resolveRootModuleFromBootstrap(string $bootstrapFile): ?string
  {
    $contents = file_get_contents($bootstrapFile);

    if ($contents === false) {
      return null;
    }

    $imports = [];

    if (preg_match_all('/^\s*use\s+([^;]+);/m', $contents, $matches)) {
      foreach ($matches[1] as $import) {
        $import = trim((string) $import);
        $alias = basename(str_replace('\\', '/', $import));
        $imports[$alias] = ltrim($import, '\\');
      }
    }

    if (!preg_match('/AssegaiFactory::create\(\s*([\\\\A-Za-z0-9_]+)::class\s*\)/', $contents, $matches)) {
      return null;
    }

    $candidate = trim($matches[1]);

    if (str_contains($candidate, '\\')) {
      return ltrim($candidate, '\\');
    }

    return $imports[$candidate] ?? null;
  }

  private function resolveRootModuleFromComposer(): ?string
  {
    $manifest = ComposerManifest::load($this->workspace);
    $autoload = $manifest['autoload']['psr-4'] ?? [];

    if (!is_array($autoload)) {
      return null;
    }

    foreach ($autoload as $namespace => $path) {
      if (!is_string($namespace) || !is_string($path)) {
        continue;
      }

      $candidate = rtrim($namespace, '\\') . '\\AppModule';

      if (class_exists($candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  private function resolveRootModuleFromSource(): ?string
  {
    $appModuleFile = Path::join($this->workspace, 'src', 'AppModule.php');

    if (!is_file($appModuleFile)) {
      return null;
    }

    $contents = file_get_contents($appModuleFile);

    if ($contents === false || !preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
      return null;
    }

    return trim($matches[1]) . '\\AppModule';
  }

  private function callStaticCoreMethod(string $class, string $method): mixed
  {
    if (!class_exists($class) || !method_exists($class, $method)) {
      throw new RuntimeException("The project does not expose the required core helper: {$class}::{$method}.");
    }

    return $class::$method();
  }

  private function newCoreInstance(string $class, mixed ...$arguments): object
  {
    if (!class_exists($class)) {
      throw new RuntimeException("The project does not expose the required core class: {$class}.");
    }

    return new $class(...$arguments);
  }
}
