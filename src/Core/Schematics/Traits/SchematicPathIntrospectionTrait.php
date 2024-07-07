<?php

namespace Assegai\Console\Core\Schematics\Traits;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;

/**
 * The schematic path introspection trait.
 *
 * @package Assegai\Console\Core\Schematics\Traits
 */
trait SchematicPathIntrospectionTrait
{
  /**
   * Get the class name.
   *
   * @return string
   */
  public function getClassName(): string
  {
    $prefix = $this->prefix ? $this->prefix . '-' : '';
    $suffix = $this->suffix ? '-' . $this->suffix : '';

    return (new Text($prefix . $this->name . $suffix))->pascalCase();
  }

  /**
   * Get the file path.
   *
   * @return string The file path
   */
  protected function getFilePath(): string
  {
    return Path::join($this->path, $this->getRelativeFilename());
  }

  /**
   * Get the filename.
   *
   * @return string The filename
   */
  protected function getFileName(): string
  {
    return $this->getClassName() . '.php';
  }

  /**
   * Get the relative filename.
   *
   * @return string The relative filename
   */
  protected function getRelativeFilename(): string
  {
    $tail = '';

    if ($this->inspector->isValidWorkspace(getcwd() ?: '')) {
      $tail = 'src';
    }

    if (property_exists($this, 'isFlat') && ! $this->isFlat ) {
      if ($this->subdirectory) {
        $tokens = explode('/', $this->subdirectory);
        foreach ($tokens as $token) {
          $tail = Path::join($tail, (new Text($token))->pascalCase());
        }
      }
      $tail = Path::join($tail, $this->properName);
    }

    return Path::join($tail, $this->getFileName());
  }

  /**
   * Get the relative local module filename.
   *
   * @return string The relative local module filename
   */
  protected function getRelativeLocalModuleFilePath(string $localModuleFilename): string
  {
    return Path::join(dirname($this->getRelativeFilename()), $localModuleFilename);
  }

  /**
   * Retrieve the resolved namespace suffix.
   *
   * @return string The resolved namespace suffix
   */
  public function getResolvedNamespaceSuffix(): string
  {
    $namespaceSuffix = '';
    if ($this->subdirectory) {
      $tokens = explode('/', $this->subdirectory);
      foreach ($tokens as $token) {
        $namespaceSuffix .= '\\' . (new Text($token))->pascalCase();
      }
    }

    return $namespaceSuffix . '\\' . $this->properName;
  }
}