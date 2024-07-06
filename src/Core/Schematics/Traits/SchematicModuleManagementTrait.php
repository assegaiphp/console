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
   * @return array{use: string[], declare: string[], provide: string[], control: string[], import: string[], export: string[], config: string[]} The array of statements for the AppModule.php file
   */
  public function getModuleUpdates(): array
  {
    return [
      'use' => [],
      'declare' => [],
      'provide' => [],
      'control' => [],
      'import' => [],
      'export' => [],
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

    if (false === $localFiles)
    {
      $this->output->writeln("<error>Failed to scan the directory: $workingDirectory</error>");
      return false;
    }

    foreach ($localFiles as $file)
    {
      if (str_ends_with($file, 'Module.php'))
      {
        return $file;
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
    // TODO: Implement updateLocalModule() method.

    $relativeLocalModuleFilename = $this->getRelativeLocalModuleFilePath($localModuleFilename);
    $modulePropertyNameMap = [
      'use' => 'use',
      'declare' => 'declarations',
      'provide' => 'providers',
      'control' => 'controllers',
      'import' => 'imports',
      'export' => 'exports',
    ];
    $moduleFileContent = file_get_contents($relativeLocalModuleFilename) ?: '';

    $bytes = 0;
    foreach ($props as $prop => $values)
    {
      $propertyName = $modulePropertyNameMap[$prop] ?? '';
      if ($prop === 'use')
      {
        // TODO: Fix the use statements
        continue;
      }

      if (! $propertyName)
      {
        continue;
      }

      $formatter = new InlineAttributePropertiesFormatter($propertyName);
      $oldValues = $formatter->extractValues($moduleFileContent ?? '');

      if (count($oldValues) + count($values) > 3)
      {
        $formatter = new StackedAttributePropertiesFormatter($propertyName);
      }

      $formatter->addValues($values);
      $moduleFileContent = preg_replace($formatter->getPattern(), $formatter->getFormatted($moduleFileContent ?? ''), $moduleFileContent ?? '');
    }

    $bytesToAdd = file_put_contents($relativeLocalModuleFilename, $moduleFileContent);
    if (false === $bytesToAdd)
    {
      $this->output->writeln("<error>Failed to write to the file: $relativeLocalModuleFilename</error>");
      return Command::FAILURE;
    }
    $bytes += $bytesToAdd;

    $this->output->writeln("<fg=bright-blue>UPDATE</> $relativeLocalModuleFilename ($bytes bytes)");
    return Command::SUCCESS;
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
    // TODO: Implement updateAppModule() method.
    $filename = Path::join('src', 'AppModule.php');
    $filePath = Path::join(getcwd() ?: '', $filename);

    if (! file_exists($filePath) )
    {
      $this->output->writeln("<error>File does not exist: $filename</error>");
      return Command::FAILURE;
    }
    $content = file_get_contents($filePath);

    if (! $content)
    {
      $this->output->writeln("<error>Could not read $filename</error>");
      return Command::FAILURE;
    }

    $content = $this->getUpdatedAppModuleContent($content, $props);

    $bytes = file_put_contents($filePath, $content);
    if (false === $bytes)
    {
      $this->output->writeln("<error>Could not write to $filename</error>");
      return Command::FAILURE;
    }

    $this->output->writeln("<fg=bright-blue>UPDATE</> src/AppModule.php ($bytes bytes)");
    return Command::SUCCESS;
  }

}