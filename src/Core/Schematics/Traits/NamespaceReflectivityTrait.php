<?php

namespace Assegai\Console\Core\Schematics\Traits;

use Assegai\Console\Util\Config\ComposerConfig;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Trait NamespaceReflectivityTrait
 *
 * @package Assegai\Console\Core\Schematics\Traits
 */
trait NamespaceReflectivityTrait
{

  /**
   * Load the namespace from the configuration file.
   *
   * @return void
   */
  public function loadNamespaceFromConfig(): void
  {
    $input = new ArgvInput();
    $output = new ConsoleOutput();
    $namespace = $this->namspace ?? '';
    $namespaceSuffix = $this->namespaceSuffix ?? '';

    $config = new ComposerConfig($input, $output);
    $config->load();

    $namespaces = $config->get('autoload.psr-4');
    foreach ($namespaces as $ns => $path) {
      if ($path === 'src/') {
        $namespace = rtrim($ns, '\\');
        if ($namespaceSuffix) {
          $namespace .= '\\' . ltrim($namespaceSuffix, '\\');
        }
        break;
      }
    }

    if (property_exists($this, 'namespace')) {
      $this->namespace = $namespace;
    }
  }
}