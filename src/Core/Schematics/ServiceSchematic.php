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
   * @return array{use?: ?string[], declarations?: ?string[], imports?: ?string[], controllers?: ?string[], providers?: ?string[], exports?: ?string[], config?: ?string[]} $data The data to update the module file with.
   */
  #[Override]
  public function getModuleUpdates(): array
  {
    return [
      'use' => [$this->namespace . '\\' . $this->getClassName()],
      'providers' => [$this->getClassName() . '::class'],
    ];
  }
}