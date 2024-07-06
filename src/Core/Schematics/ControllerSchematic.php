<?php

namespace Assegai\Console\Core\Schematics;

use Override;

/**
 * Class ControllerSchematic
 *
 * @package Assegai\Console\Core\Schematics
 */
class ControllerSchematic extends AbstractClassSchematic
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this->suffix = 'controller';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = ['Assegai\Core\Attributes\Controller'];
    $this->attributes = ["Controller('$this->name')"];
  }

  /**
   * @inheritDoc
   */
  #[Override]
  public function getModuleUpdates(): array
  {
    return [
      'use' => [$this->namespace . '\\' . $this->getClassName()],
      'declare' => [],
      'provide' => [],
      'control' => [$this->getClassName() . '::class'],
      'import' => [],
      'export' => [],
      'config' => [],
    ];
  }
}