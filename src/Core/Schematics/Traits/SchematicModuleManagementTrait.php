<?php

namespace Assegai\Console\Core\Schematics\Traits;

use Assegai\Console\Core\Formatting\InlineAttributePropertiesFormatter;
use Assegai\Console\Core\Formatting\StackedAttributePropertiesFormatter;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;

trait SchematicModuleManagementTrait
{
  /**
   * For the AppModule.php file update.
   *
   * @return array{use: ?string[], declarations: ?string[], providers: ?string[], controllers: ?string[], imports: ?string[], exports: ?string[], config: ?string[]} The array of statements for the AppModule.php file
   */
  public function getModuleUpdates(): array
  {
    return [
      'use' => [],
      'declarations' => [],
      'providers' => [],
      'controllers' => [],
      'imports' => [],
      'exports' => [],
      'config' => [],
    ];
  }

  /**
   * Retrieve the local module filename if it exists.
   *
   * @return false|string The local module filename, or false if not found
   */
  private function getLocalModuleFilename(): false|string
  {
    $workingDirectory = dirname($this->getFilePath());
    $localFiles = scandir($workingDirectory);

    if (false === $localFiles) {
      $this->output->writeln("<error>Failed to scan the directory: $workingDirectory</error>");
      return false;
    }

    foreach ($localFiles as $file) {
      if (str_ends_with($file, 'Module.php')) {
        $filename = Path::join($workingDirectory, $file);
        return str_replace(Path::join(getcwd() ?: '', 'src'), '', $filename);
      }
    }

    return false;
  }

  /**
   * Update the local module file.
   *
   * @param string $localModuleFilename The local module filename
   * @param array{use: ?string[], declarations: ?string[], providers: ?string[], controllers: ?string[], imports: ?string[], exports: ?string[], config: ?string[]} $props
   * @return int The status of the update
   */
  protected function updateLocalModule(
    string $localModuleFilename,
    array $props
  ): int
  {
    return update_module_file($props, $localModuleFilename);
  }

  /**
   * Update the AppModule.php file.
   *
   * @param array{use?: ?string[], declarations?: ?string[], providers?: ?string[], controllers?: ?string[], imports?: ?string[], exports?: ?string[], config?: ?string[]} $props
   * @return int The status of the update.
   */
  protected function updateAppModule(
    array $props = [
      'use' => [],
      'declarations' => [],
      'providers' => [],
      'controllers' => [],
      'imports' => [],
      'exports' => [],
      'config' => [],
    ]
  ): int
  {
    return update_module_file($props);
  }
}