<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Closure;
use RuntimeException;
use Assegai\Console\Core\Interfaces\SchematicInterface;

class SchematicDefinition
{
  /**
   * @param string[] $aliases
   * @param SchematicArgumentDefinition[] $arguments
   * @param SchematicOptionDefinition[] $options
   * @param array<string, mixed> $metadata
   * @param Closure(SchematicContext): SchematicInterface $factory
   */
  public function __construct(
    public string $name,
    public array $aliases,
    public string $description,
    public bool $requiresWorkspace,
    public string $sourceType,
    public string $source,
    public string $kind,
    public array $arguments,
    public array $options,
    public array $metadata,
    public Closure $factory,
  )
  {
  }

  public function createSchematic(SchematicContext $context): SchematicInterface
  {
    $schematic = ($this->factory)($context);

    if (! $schematic instanceof SchematicInterface) {
      throw new RuntimeException(sprintf(
        'Schematic "%s" from %s did not resolve to a valid schematic.',
        $this->name,
        $this->source,
      ));
    }

    return $schematic;
  }

  public function matches(string $nameOrAlias): bool
  {
    if ($this->name === $nameOrAlias) {
      return true;
    }

    return in_array($nameOrAlias, $this->aliases, true);
  }
}
