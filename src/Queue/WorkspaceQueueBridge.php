<?php

namespace Assegai\Console\Queue;

use Assegai\Console\Api\WorkspaceApiBridge;
use Assegai\Common\Interfaces\Queues\QueueInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use RuntimeException;

class WorkspaceQueueBridge
{
  private bool $workspaceAutoloadLoaded = false;
  private bool $frameworkBootstrapped = false;
  /** @var string[]|null */
  private ?array $providerClasses = null;
  private readonly WorkspaceApiBridge $apiBridge;

  public function __construct(private readonly string $workspace)
  {
    $this->apiBridge = new WorkspaceApiBridge($workspace);
  }

  /**
   * @return array<int, array{path: string, driver: string, driverClass: string, name: string, processors: string[]}>
   */
  public function listQueues(): array
  {
    return $this->withWorkspaceContext(function (): array {
      $definitions = $this->collectQueueDefinitions();
      $processorsByPath = [];

      foreach ($this->discoverProcessors() as $processor) {
        $processorsByPath[$processor['path']][] = $processor['class'];
      }

      foreach ($definitions as &$definition) {
        $definition['processors'] = $processorsByPath[$definition['path']] ?? [];
      }

      unset($definition);

      return $definitions;
    });
  }

  /**
   * @return array{processorClass: string, processorMethod: string, processedJobs: int}
   */
  public function work(
    ?string $connectionPath = null,
    ?string $processorClass = null,
    int $sleepMilliseconds = 500,
    bool $once = false,
    int $maxJobs = 0,
    bool $stopWhenEmpty = false,
    ?callable $onProcessed = null,
    ?callable $onError = null,
  ): array {
    return $this->withWorkspaceContext(function () use (
      $connectionPath,
      $processorClass,
      $sleepMilliseconds,
      $once,
      $maxJobs,
      $stopWhenEmpty,
      $onProcessed,
      $onError,
    ): array {
      $definition = $this->resolveQueueDefinition($connectionPath);
      $processor = $this->resolveProcessorMetadata($definition['path'], $processorClass);
      $processorInstance = $this->resolveProcessorInstance($processor['class']);
      $processorMethod = $processor['method'];
      $queue = $this->instantiateQueue($definition);
      $processedJobs = 0;
      $sleepMicroseconds = max(0, $sleepMilliseconds) * 1000;

      while (true) {
        $result = $queue->process(function (object $job) use ($processorInstance, $processorMethod): void {
          $processorInstance->{$processorMethod}($job);
        });

        $job = $result->getJob();

        if ($job === null) {
          if ($stopWhenEmpty || $once) {
            break;
          }

          if ($sleepMicroseconds > 0) {
            usleep($sleepMicroseconds);
          }

          continue;
        }

        if ($result->isError()) {
          foreach ($result->getErrors() as $error) {
            if ($onError !== null) {
              $onError($error, $job, $definition, $processor);
            }
          }

          if ($once) {
            break;
          }

          continue;
        }

        $processedJobs++;

        if ($onProcessed !== null) {
          $onProcessed($job, $definition, $processor);
        }

        if ($once || ($maxJobs > 0 && $processedJobs >= $maxJobs)) {
          break;
        }
      }

      return [
        'processorClass' => $processor['class'],
        'processorMethod' => $processorMethod,
        'processedJobs' => $processedJobs,
      ];
    });
  }

  /**
   * @return array<int, array{path: string, class: string, method: string}>
   */
  public function discoverProcessors(): array
  {
    $this->bootstrapFramework();
    $queueProcessorAttribute = 'Assegai\\Core\\Queues\\Attributes\\QueueProcessor';
    $processors = [];

    foreach ($this->discoverProviderClasses() as $providerClass) {
      if (!class_exists($providerClass)) {
        continue;
      }

      $reflectionClass = new ReflectionClass($providerClass);
      $attributes = $reflectionClass->getAttributes($queueProcessorAttribute);

      if ($attributes === []) {
        continue;
      }

      $attribute = $attributes[0]->newInstance();
      $path = trim((string) ($attribute->path ?? ''));
      $method = trim((string) ($attribute->method ?? 'process'));

      if ($path === '') {
        continue;
      }

      $processors[] = [
        'path' => $path,
        'class' => $providerClass,
        'method' => $method === '' ? 'process' : $method,
      ];
    }

    return $processors;
  }

  /**
   * @return array<int, array{path: string, driver: string, driverClass: string, name: string, config: array<string, mixed>}>
   */
  private function collectQueueDefinitions(): array
  {
    $queueConfig = $this->loadQueueConfiguration();
    $drivers = $queueConfig['drivers'] ?? [];
    $connections = $queueConfig['connections'] ?? [];

    if (!is_array($drivers) || !is_array($connections)) {
      return [];
    }

    $definitions = [];

    foreach ($connections as $driver => $namedConnections) {
      if (!is_string($driver) || !is_array($namedConnections)) {
        continue;
      }

      $driverClass = $drivers[$driver] ?? null;

      if (!is_string($driverClass) || $driverClass === '') {
        continue;
      }

      foreach ($namedConnections as $name => $config) {
        if (!is_string($name) || !is_array($config)) {
          continue;
        }

        $config['name'] ??= $name;
        $definitions[] = [
          'path' => $driver . '.' . $name,
          'driver' => $driver,
          'driverClass' => $driverClass,
          'name' => (string) $config['name'],
          'config' => $config,
        ];
      }
    }

    return $definitions;
  }

  /**
   * @param array{path: string, driver: string, driverClass: string, name: string, config: array<string, mixed>} $definition
   * @return QueueInterface<mixed>
   */
  private function instantiateQueue(array $definition): QueueInterface
  {
    $driverClass = $definition['driverClass'];

    if (!class_exists($driverClass)) {
      throw new RuntimeException("Queue driver class '{$driverClass}' was not found.");
    }

    if (!is_subclass_of($driverClass, QueueInterface::class)) {
      throw new RuntimeException("Queue driver class '{$driverClass}' must implement QueueInterface.");
    }

    return $driverClass::create($definition['config']);
  }

  /**
   * @return array{path: string, class: string, method: string}
   */
  private function resolveProcessorMetadata(string $connectionPath, ?string $processorClass = null): array
  {
    if (is_string($processorClass) && trim($processorClass) !== '') {
      return [
        'path' => $connectionPath,
        'class' => trim($processorClass),
        'method' => $this->detectProcessorMethod(trim($processorClass), null),
      ];
    }

    $matchedProcessors = array_values(array_filter(
      $this->discoverProcessors(),
      static fn(array $processor): bool => $processor['path'] === $connectionPath
    ));

    if ($matchedProcessors === []) {
      throw new RuntimeException(
        "No queue processor is registered for '{$connectionPath}'. Add #[QueueProcessor('{$connectionPath}')] to an injectable provider or pass --processor."
      );
    }

    if (count($matchedProcessors) > 1) {
      $classes = implode(', ', array_map(static fn(array $processor): string => $processor['class'], $matchedProcessors));
      throw new RuntimeException(
        "Multiple queue processors are registered for '{$connectionPath}': {$classes}. Pass --processor to choose one."
      );
    }

    $processor = $matchedProcessors[0];
    $processor['method'] = $this->detectProcessorMethod($processor['class'], $processor['method']);

    return $processor;
  }

  /**
   * @param string|null $connectionPath
   * @return array{path: string, driver: string, driverClass: string, name: string, config: array<string, mixed>}
   */
  private function resolveQueueDefinition(?string $connectionPath): array
  {
    $definitions = $this->collectQueueDefinitions();

    if ($definitions === []) {
      throw new RuntimeException('No queue connections are configured. Add config/queues.php first.');
    }

    if ($connectionPath === null || trim($connectionPath) === '') {
      if (count($definitions) === 1) {
        return $definitions[0];
      }

      $paths = implode(', ', array_map(static fn(array $definition): string => $definition['path'], $definitions));
      throw new RuntimeException("Multiple queue connections are configured. Choose one of: {$paths}");
    }

    foreach ($definitions as $definition) {
      if ($definition['path'] === trim($connectionPath)) {
        return $definition;
      }
    }

    throw new RuntimeException("Queue connection '{$connectionPath}' was not found in config/queues.php.");
  }

  private function resolveProcessorInstance(string $processorClass): object
  {
    $this->bootstrapFramework();
    $injector = $this->callStaticCoreMethod('Assegai\\Core\\Injector', 'getInstance');

    if (!class_exists($processorClass)) {
      throw new RuntimeException("Queue processor class '{$processorClass}' was not found.");
    }

    try {
      $instance = $injector->resolve($processorClass);

      if (is_object($instance)) {
        return $instance;
      }
    } catch (\Throwable) {
      // Fall back to direct construction below for simple processors.
    }

    $reflectionClass = new ReflectionClass($processorClass);

    if (!$reflectionClass->isInstantiable()) {
      throw new RuntimeException("Queue processor class '{$processorClass}' is not instantiable.");
    }

    $constructor = $reflectionClass->getConstructor();

    if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
      throw new RuntimeException(
        "Queue processor class '{$processorClass}' could not be resolved through the container. Make sure it is injectable and registered as a provider."
      );
    }

    return $reflectionClass->newInstance();
  }

  private function detectProcessorMethod(string $processorClass, ?string $preferredMethod = null): string
  {
    if (!class_exists($processorClass)) {
      throw new RuntimeException("Queue processor class '{$processorClass}' was not found.");
    }

    $reflectionClass = new ReflectionClass($processorClass);
    $candidates = $preferredMethod !== null && $preferredMethod !== ''
      ? [$preferredMethod, 'process', 'handle', '__invoke']
      : ['process', 'handle', '__invoke'];

    foreach ($candidates as $candidate) {
      if ($reflectionClass->hasMethod($candidate)) {
        return $candidate;
      }
    }

    throw new RuntimeException(
      "Queue processor class '{$processorClass}' must define a '{$preferredMethod}', 'process', 'handle', or '__invoke' method."
    );
  }

  private function bootstrapFramework(): void
  {
    if ($this->frameworkBootstrapped) {
      return;
    }

    $this->loadWorkspaceAutoload();
    $this->primeCliHttpState();

    $injector = $this->requireCoreHelperObject('Assegai\Core\Injector', 'getInstance', ['add', 'resolve']);
    $moduleManager = $this->requireCoreHelperObject('Assegai\Core\ModuleManager', 'getInstance');
    $rootModuleClass = $this->requireClassString($this->apiBridge->resolveRootModuleClass(), 'root module');

    $dependencies = [
      'Assegai\\Core\\Config\\AppConfig' => $this->newCoreInstance('Assegai\\Core\\Config\\AppConfig'),
      'Assegai\\Core\\Config\\ComposerConfig' => $this->newCoreInstance('Assegai\\Core\\Config\\ComposerConfig'),
      'Assegai\\Core\\Config\\ProjectConfig' => $this->newCoreInstance('Assegai\\Core\\Config\\ProjectConfig'),
      'Assegai\\Core\\Http\\Requests\\Request' => $this->callStaticCoreMethod('Assegai\\Core\\Http\\Requests\\Request', 'getInstance'),
      'Assegai\\Core\\Http\\Responses\\Response' => $this->callStaticCoreMethod('Assegai\\Core\\Http\\Responses\\Response', 'getInstance'),
      'Assegai\\Core\\ModuleManager' => $moduleManager,
      'Assegai\\Core\\Injector' => $injector,
      'Psr\\Log\\LoggerInterface' => new NullLogger(),
    ];

    /** @var callable(string, mixed): void $registerDependency */
    $registerDependency = [$injector, 'add'];

    foreach ($dependencies as $entryId => $dependency) {
      $registerDependency($entryId, $dependency);
    }

    if (
      method_exists($moduleManager, 'setRootModuleClass') &&
      method_exists($moduleManager, 'buildModuleTokensList') &&
      method_exists($moduleManager, 'buildProviderTokensList')
    ) {
      call_user_func([$moduleManager, 'setRootModuleClass'], $rootModuleClass);
      call_user_func([$moduleManager, 'buildModuleTokensList'], $rootModuleClass);
      call_user_func([$moduleManager, 'buildProviderTokensList']);
    }

    $this->frameworkBootstrapped = true;
  }

  /**
   * @return string[]
   */
  private function discoverProviderClasses(): array
  {
    if ($this->providerClasses !== null) {
      return $this->providerClasses;
    }

    $moduleManager = $this->callStaticCoreMethod('Assegai\\Core\\ModuleManager', 'getInstance');

    if (method_exists($moduleManager, 'getProviderTokens')) {
      /** @var callable(): mixed $loadProviderTokens */
      $loadProviderTokens = [$moduleManager, 'getProviderTokens'];
      $providerTokens = $loadProviderTokens();

      if (is_array($providerTokens) && $providerTokens !== []) {
        return $this->providerClasses = array_values(array_filter(
          array_keys($providerTokens),
          static fn(mixed $providerClass): bool => is_string($providerClass) && $providerClass !== ''
        ));
      }
    }

    return $this->providerClasses = $this->discoverProviderClassesFromModules(
      $this->requireClassString($this->apiBridge->resolveRootModuleClass(), 'root module')
    );
  }

  /**
   * @param class-string $rootModuleClass
   * @return string[]
   */
  private function discoverProviderClassesFromModules(string $rootModuleClass): array
  {
    $moduleAttributeClass = 'Assegai\\Core\\Attributes\\Modules\\Module';
    $providers = [];
    $visited = [];
    $stack = [$rootModuleClass];

    while ($stack !== []) {
      $moduleClass = array_pop($stack);

      if (!is_string($moduleClass) || isset($visited[$moduleClass]) || !class_exists($moduleClass)) {
        continue;
      }

      $visited[$moduleClass] = true;
      $reflectionClass = new ReflectionClass($moduleClass);
      $attributes = $reflectionClass->getAttributes($moduleAttributeClass);

      if ($attributes === []) {
        continue;
      }

      $arguments = $attributes[0]->getArguments();

      foreach ($arguments['providers'] ?? [] as $providerClass) {
        if (is_string($providerClass) && $providerClass !== '') {
          $providers[$providerClass] = $providerClass;
        }
      }

      foreach (['imports', 'exports'] as $moduleList) {
        foreach ($arguments[$moduleList] ?? [] as $candidate) {
          if (!is_string($candidate) || $candidate === '' || !class_exists($candidate)) {
            continue;
          }

          $candidateReflection = new ReflectionClass($candidate);

          if ($candidateReflection->getAttributes($moduleAttributeClass) !== []) {
            $stack[] = $candidate;
          }
        }
      }
    }

    return array_values($providers);
  }


  /**
   * @return array<string, mixed>
   */
  private function loadQueueConfiguration(): array
  {
    $this->loadWorkspaceAutoload();
    $configFile = $this->workspace . '/config/queues.php';

    if (!is_file($configFile)) {
      return [];
    }

    $config = require $configFile;

    return is_array($config) ? $config : [];
  }

  private function primeCliHttpState(): void
  {
    $_SERVER['REQUEST_METHOD'] ??= 'GET';
    $_SERVER['REQUEST_URI'] ??= '/';
    $_SERVER['CONTENT_TYPE'] ??= '';
    $_GET['path'] ??= '/';
  }

  private function loadWorkspaceAutoload(): void
  {
    if ($this->workspaceAutoloadLoaded) {
      return;
    }

    $autoloadFile = $this->workspace . '/vendor/autoload.php';

    if (!is_file($autoloadFile)) {
      throw new RuntimeException('This project is missing vendor/autoload.php. Run composer install first.');
    }

    require_once $autoloadFile;
    $this->workspaceAutoloadLoaded = true;
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

  /**
   * @param list<string> $requiredMethods
   */
  private function requireCoreHelperObject(string $class, string $method, array $requiredMethods = []): object
  {
    $instance = $this->callStaticCoreMethod($class, $method);

    if (!is_object($instance)) {
      throw new RuntimeException("The project did not return a valid object from {$class}::{$method}.");
    }

    foreach ($requiredMethods as $requiredMethod) {
      if (!method_exists($instance, $requiredMethod)) {
        throw new RuntimeException("The project does not expose the required core helper method: {$class}::{$requiredMethod}.");
      }
    }

    return $instance;
  }

  /**
   * @return class-string
   */
  private function requireClassString(string $class, string $label = 'class'): string
  {
    if ($class === '' || !class_exists($class)) {
      throw new RuntimeException("The project does not expose the required {$label}: {$class}.");
    }

    return $class;
  }


  private function withWorkspaceContext(callable $callback): mixed
  {
    $previousWorkingDirectory = getcwd() ?: $this->workspace;

    if (!@chdir($this->workspace)) {
      throw new RuntimeException("Failed to switch into workspace '{$this->workspace}'.");
    }

    try {
      return $callback();
    } finally {
      @chdir($previousWorkingDirectory);
    }
  }
}
