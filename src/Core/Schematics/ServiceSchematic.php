<?php

namespace Assegai\Console\Core\Schematics;

use Override;

/**
 * A service schematic.
 *
 * @package Assegai\Console\Core\Schematics
 */
class ServiceSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'service';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = ['Assegai\Core\Attributes\Injectable'];
    $this->attributes = ['Injectable'];
  }

  /**
   * @inheritDoc
   */
  #[Override]
  public function getModuleUpdates(): array
  {
    return [
      'use' => [],
      'declare' => [],
      'provide' => [$this->getClassName() . '::class'],
      'control' => [],
      'import' => [],
      'export' => [],
      'config' => [],
    ];
  }
}