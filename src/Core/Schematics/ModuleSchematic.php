<?php

namespace Assegai\Console\Core\Schematics;

class ModuleSchematic extends AbstractClassSchematic
{
  public function configure(): void
  {
    $this->suffix = 'module';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = ['Assegai\Core\Attributes\Modules\Module'];
    $this->attributes = [<<<PHP
Module(
  providers: [],
  controllers: [],
  imports: [],
)
PHP];
  }
}