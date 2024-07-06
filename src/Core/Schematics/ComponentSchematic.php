<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Util\Text;
use Override;

/**
 * Class ComponentSchematic
 *
 * @package Assegai\Console\Core\Schematics
 */
class ComponentSchematic extends AbstractClassSchematic
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $name = new Text($this->name);
    $selector = 'app-' . $name->kebabCase();
    $templateUrl = './' . $name->pascalCase() . 'Component.twig';
    $styleUrl = './' . $name->pascalCase() . 'Component.css';
    $this->suffix = 'component';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = ['Assegai\Core\Attributes\Component'];
    $this->attributes = [<<<COMPONENT
Component(
  selector: '$selector',
  templateUrl: '$templateUrl',
  styleUrls: ['$styleUrl'])
COMPONENT
];
  }

  /**
   * @inheritDoc
   */
  #[Override]
  public function getModuleUpdates(): array
  {
    return [
      'use' => [$this->namespace . '\\' . $this->getClassName()],
      'declare' => [$this->getClassName() . '::class'],
      'provide' => [],
      'control' => [],
      'import' => [],
      'export' => [],
      'config' => [],
    ];
  }
}