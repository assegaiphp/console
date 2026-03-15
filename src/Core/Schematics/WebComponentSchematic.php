<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Assegai\Console\WebComponents\WebComponentConfig;
use Assegai\Console\WebComponents\WebComponentScaffolder;
use Symfony\Component\Console\Command\Command;

class WebComponentSchematic extends AbstractFileSchematic
{
  public function prepareBuild(): int
  {
    return WebComponentScaffolder::ensureRuntime($this->path, $this->output);
  }

  protected function getContent(): string
  {
    $tagName = WebComponentConfig::makeSelector($this->path, $this->name);
    $componentName = (new Text($this->name))->pascalCase();
    $filename = $this->getFilePath();
    $runtimeImport = WebComponentScaffolder::getRuntimeImportPath($this->path, $filename);

    return WebComponentScaffolder::renderComponentTemplate($componentName, $tagName, $runtimeImport);
  }

  protected function getFileName(): string
  {
    return (new Text($this->name))->pascalCase() . 'Component.wc.ts';
  }

  protected function getRelativeFilename(): string
  {
    $segments = ['src', 'WebComponents'];

    if ($this->subdirectory) {
      foreach (array_filter(explode('/', $this->subdirectory)) as $segment) {
        $segments[] = (new Text($segment))->pascalCase();
      }
    }

    $segments[] = (new Text($this->name))->pascalCase();
    $segments[] = $this->getFileName();

    return Path::join(...$segments);
  }

  public function finalizeBuild(): int
  {
    return Command::SUCCESS;
  }
}
