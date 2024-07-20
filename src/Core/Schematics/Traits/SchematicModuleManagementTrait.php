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
   * @return array{use: string[], declarations: string[], providers: string[], controllers: string[], imports: string[], exports: string[], config: string[]} The array of statements for the AppModule.php file
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
   * Get the updated AppModule.php content.
   *
   * @param string $content The content of the AppModule.php file
   * @param array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} $props The properties for the update
   * @return string The updated content of the AppModule.php file
   */
  protected function getUpdatedAppModuleContent(string $content, array $props): string
  {
    $output = $content;

    foreach ($props as $prop => $values)
    {
      if ($prop === 'use')
      {
        // TODO: Replace the use statements

        continue;
      }

      $matches = [];
      $pattern = "/$prop: \[([\w:,\s]*)]/";
      $oldValues = [];
      if (preg_match($pattern, $output ?? '', $matches))
      {
        $oldValues = explode(',', $matches[1] ?? '');
      }

      $newValues = [...$oldValues, ...$values];
      if ((count($values) + count($oldValues)) > 3)
      {
        $replacements = "\n" . implode(",\n", array_map(fn($value) => "    $value", $newValues)) . "\n  ";
      }
      else
      {
        $replacements = implode(', ', $newValues);
      }

      $output = preg_replace($pattern, "$prop: [$replacements]", $output ?? '');
    }

    return $output ?? '';
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
   * @param array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} $props
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
   * @param array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} $props
   * @return int The status of the update.
   */
  protected function updateAppModule(
    array $props = [
      'use' => [],
      'declare' => [],
      'provide' => [],
      'control' => [],
      'import' => [],
      'export' => [],
      'config' => [],
    ]
  ): int
  {
    return update_module_file($props);
  }
}