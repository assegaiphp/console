<?php

namespace Assegai\Console\Core\Schematics;

class GuardSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'guard';
    $this->imports = [
      'Assegai\Core\Interfaces\ICanActivate',
      'Assegai\Core\Interfaces\IExecutionContext',
      'Assegai\Core\Attributes\Injectable',
    ];
    $this->attributes = ['Injectable'];
    $this->methods = [
    <<<PHP
  public function canActivate(IExecutionContext \$context): bool
  {
    // TODO: Implement canActivate()
    return true;
  }
PHP
    ];
    $this->interfaces = ['ICanActivate'];
    $this->regex[] = [
      'pattern' => '/{\s+public/',
      'replacement' => "{\n  public",
    ];
  }
}