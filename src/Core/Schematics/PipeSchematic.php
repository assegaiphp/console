<?php

namespace Assegai\Console\Core\Schematics;

/**
 * A pipe schematic. This class is used to generate Pipe classes.
 *
 * @package Assegai\Console\Core\Schematics
 */
class PipeSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'pipe';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = [
      'Assegai\Core\Interfaces\IPipeTransform',
      'Assegai\Core\Attributes\Injectable',
      'stdClass'
    ];
    $this->attributes = ['Injectable'];
    $this->interfaces = ['IPipeTransform'];
    $this->regex[] = [
      'pattern' => '/{\s+public/',
      'replacement' => "{\n  public",
    ];
    $this->methods = [
      <<<PHP
  public function transform(mixed \$value, array|stdClass|null \$metaData = null): mixed
  {
    // TODO: implement transform()

    return \$value;
  }
PHP
    ];
  }
}