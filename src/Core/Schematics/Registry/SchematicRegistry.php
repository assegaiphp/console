<?php

namespace Assegai\Console\Core\Schematics\Registry;

use RuntimeException;

class SchematicRegistry
{
  /** @var array<string, SchematicDefinition> */
  private array $definitions = [];
  /** @var array<string, string> */
  private array $aliases = [];

  public function register(SchematicDefinition $definition): void
  {
    if (isset($this->definitions[$definition->name])) {
      throw new RuntimeException(sprintf(
        'Schematic name collision for "%s" between %s and %s.',
        $definition->name,
        $this->definitions[$definition->name]->source,
        $definition->source,
      ));
    }

    foreach ($definition->aliases as $alias) {
      if ($alias === '') {
        continue;
      }

      if (isset($this->definitions[$alias])) {
        throw new RuntimeException(sprintf(
          'Schematic alias collision for "%s" between %s and %s.',
          $alias,
          $this->definitions[$alias]->source,
          $definition->source,
        ));
      }

      if (isset($this->aliases[$alias])) {
        $existing = $this->definitions[$this->aliases[$alias]] ?? null;

        throw new RuntimeException(sprintf(
          'Schematic alias collision for "%s" between %s and %s.',
          $alias,
          $existing?->source ?? $this->aliases[$alias],
          $definition->source,
        ));
      }
    }

    $this->definitions[$definition->name] = $definition;

    foreach ($definition->aliases as $alias) {
      if ($alias === '') {
        continue;
      }

      $this->aliases[$alias] = $definition->name;
    }
  }

  /**
   * @param SchematicDefinition[] $definitions
   */
  public function registerAll(array $definitions): void
  {
    foreach ($definitions as $definition) {
      $this->register($definition);
    }
  }

  public function get(string $nameOrAlias): ?SchematicDefinition
  {
    $canonicalName = $this->aliases[$nameOrAlias] ?? $nameOrAlias;

    return $this->definitions[$canonicalName] ?? null;
  }

  /**
   * @return SchematicDefinition[]
   */
  public function all(): array
  {
    ksort($this->definitions);
    return array_values($this->definitions);
  }
}
