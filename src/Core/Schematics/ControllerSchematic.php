<?php

namespace Assegai\Console\Core\Schematics;

use Override;

class ControllerSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'controller';
    $this->namespaceSuffix = $this->properName;
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