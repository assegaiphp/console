<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Override;
use Symfony\Component\Console\Command\Command;

class QueueProcessorSchematic extends AbstractClassSchematic
{
  protected string $queueConnection = 'driver.connection';
  protected string $rawJobReference = '';

  public function configure(): void
  {
    $queueConnection = trim((string) ($this->input->getOption('queue') ?: 'driver.connection'));

    if ($queueConnection === '') {
      $queueConnection = 'driver.connection';
    }

    $this->queueConnection = $queueConnection;
    $this->rawJobReference = trim((string) ($this->input->getOption('job') ?: ''));
    $this->suffix = 'processor';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = [
      'Assegai\Core\Attributes\Injectable',
      'Assegai\Core\Queues\Attributes\QueueProcessor',
    ];
    $this->methods = [$this->buildProcessMethod('object')];
  }

  public function getClassAttributes(): string
  {
    return "#[Injectable]\n#[QueueProcessor('{$this->queueConnection}')]\n";
  }

  public function prepareBuild(): int
  {
    $this->loadNamespaceFromConfig();

    [$jobType, $jobImport] = $this->resolveJobReference();

    if (is_string($jobImport) && !in_array($jobImport, $this->imports, true)) {
      $this->imports[] = $jobImport;
    }

    $this->methods = [$this->buildProcessMethod($jobType)];

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  #[Override]
  public function getModuleUpdates(): array
  {
    return [
      'use' => [$this->namespace . '\\' . $this->getClassName()],
      'providers' => [$this->getClassName() . '::class'],
    ];
  }

  private function buildProcessMethod(string $jobType): string
  {
    return <<<PHP
  public function process({$jobType} \$job): void
  {
    // Replace this with the real job handling work for the queue.
  }
PHP;
  }

  /**
   * @return array{0: string, 1: ?string}
   */
  private function resolveJobReference(): array
  {
    $jobReference = trim($this->rawJobReference);

    if ($jobReference === '' || strtolower($jobReference) === 'object') {
      return ['object', null];
    }

    $jobReference = str_replace('/', '\\', $jobReference);
    $isFullyQualified = str_starts_with($jobReference, '\\');
    $jobReference = trim($jobReference, '\\');

    if ($jobReference === '') {
      return ['object', null];
    }

    if (!str_contains($jobReference, '\\')) {
      $type = (new Text($jobReference))->pascalCase();
      $import = $this->resolveImplicitJobImport($type);

      return [$type, $import];
    }

    $segments = array_values(array_filter(explode('\\', $jobReference)));
    $segments = array_map(static fn(string $segment): string => (new Text($segment))->pascalCase(), $segments);

    $import = $isFullyQualified
      ? implode('\\', $segments)
      : $this->getRootNamespace() . '\\' . implode('\\', $segments);

    $type = end($segments) ?: 'object';

    if ($import === $this->namespace . '\\' . $type) {
      return [$type, null];
    }

    return [$type, $import];
  }

  private function resolveImplicitJobImport(string $type): ?string
  {
    $featureJobsDirectory = Path::join($this->getFeatureDirectory(), 'Jobs');

    if (is_dir($featureJobsDirectory)) {
      return $this->namespace . '\\Jobs\\' . $type;
    }

    $rootJobsDirectory = Path::join($this->path, 'src', 'Jobs');

    if (is_dir($rootJobsDirectory)) {
      return $this->getRootNamespace() . '\\Jobs\\' . $type;
    }

    return null;
  }

  private function getFeatureDirectory(): string
  {
    $directory = Path::join($this->path, 'src');

    if ($this->subdirectory !== '') {
      foreach (explode('/', $this->subdirectory) as $token) {
        $directory = Path::join($directory, (new Text($token))->pascalCase());
      }
    }

    return Path::join($directory, $this->properName);
  }

  private function getRootNamespace(): string
  {
    $suffix = $this->namespaceSuffix ? '\\' . ltrim($this->namespaceSuffix, '\\') : '';

    if ($suffix !== '' && str_ends_with($this->namespace, $suffix)) {
      return substr($this->namespace, 0, -strlen($suffix));
    }

    return $this->namespace;
  }
}
