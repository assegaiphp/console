<?php

namespace Assegai\Console\Core\Schematics;

use Override;

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

  /**
   * @inheritDoc
   */
  #[Override]
  public function finalizeBuild(): int
  {
    return update_module_file([
      'use' => ["$this->namespace\\{$this->properName}Module"],
      'imports' => ["{$this->properName}Module::class"],
    ]);
  }
}