<?php

namespace Assegai\Console\Core\Schematics\Traits;

use Assegai\Console\Util\Config\ComposerConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;

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
    $input = property_exists($this, 'input') && $this->input instanceof InputInterface
      ? $this->input
      : new ArgvInput();
    $output = property_exists($this, 'output') && $this->output instanceof OutputInterface
      ? $this->output
      : new ConsoleOutput();
    $workingDirectory = property_exists($this, 'path') && is_string($this->path)
      ? $this->path
      : null;
    $namespace = property_exists($this, 'namespace') && is_string($this->namespace)
      ? $this->namespace
      : '';
    $namespaceSuffix = $this->namespaceSuffix ?? '';

    $config = new ComposerConfig($input, $output, $workingDirectory);

    if ($config->load() !== Command::SUCCESS) {
      return;
    }

    $namespaces = $config->get('autoload.psr-4', []);

    if (!is_array($namespaces)) {
      return;
    }

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
