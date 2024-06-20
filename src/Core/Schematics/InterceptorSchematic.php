<?php

namespace Assegai\Console\Core\Schematics;

/**
 * An interceptor schematic. This class is used to generate Interceptor classes.
 *
 * @package Assegai\Console\Core\Schematics
 */
class InterceptorSchematic extends AbstractClassSchematic
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this->suffix = 'interceptor';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = [
      'Assegai\Core\ExecutionContext',
      'Assegai\Core\Attributes\Injectable',
      'Assegai\Core\Interfaces\IAssegaiInterceptor'
    ];
    $this->attributes = ['Injectable'];
    $this->interfaces = ['IAssegaiInterceptor'];
    $this->regex[] = [
      'pattern' => '/{\s+public/',
      'replacement' => "{\n  public",
    ];
    $this->methods = [
      <<<PHP
  public function intercept(ExecutionContext \$context): ?callable {
    // TODO: Implement intercept() method
    
    return function () use (\$context) {
      return \$context;
    };
  }
PHP
    ];
  }
}