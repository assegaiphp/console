<?php

use Assegai\Console\Util\Path;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Console\Command\Command;

/**
 * Copies a directory recursively.
 *
 * @param string $source The source directory.
 * @param string $destination The destination directory.
 * @return bool True if the directory was copied successfully, false otherwise.
 */
function copy_directory(string $source, string $destination): bool
{
  $directory = dir($source);

  if (false === $directory) {
    return false;
  }

  if (!is_dir($destination)) {
    mkdir($destination, 0755, true);
  }

  while (false !== ($entry = $directory->read())) {
    if ($entry == '.' || $entry == '..') {
      continue;
    }
    if (is_dir($source . '/' . $entry)) {
      copy_directory($source . '/' . $entry, $destination . '/' . $entry);
      continue;
    }
    if (false === copy($source . '/' . $entry, $destination . '/' . $entry)) {
      return false;
    }
  }
  $directory->close();

  return true;
}

/**
 * Converts an array to a string.
 *
 * @param array<string, mixed> $array The array to convert.
 * @return false|string The string representation of the array.
 */
function array_to_string(array $array): false|string
{
  $output = json_encode($array, JSON_PRETTY_PRINT);

  if (false === $output) {
    return false;
  }

  $output = str_replace('{', '[', $output);
  $output = str_replace('}', ']', $output);
  return str_replace('":', '" =>', $output);
}

/**
 * Checks if a program is installed.
 *
 * @param string $programName The name of the program.
 * @return bool True if the program is installed, false otherwise.
 */
function is_installed(string $programName): bool
{
  return ! empty(`which $programName`);
}

if (! function_exists('format_bytes') ) {
  /**
   * Formats bytes into a human-readable string.
   *
   * @param int $bytes The number of bytes.
   * @return string The formatted string.
   */
  function format_bytes(int $bytes): string
  {
    $units = ['bytes', 'kb', 'mb', 'gb', 'tb'];
    $unit = ' bytes';

    for ($exponent = 0; $exponent < count($units); $exponent++) {
      if ($bytes < 1024) {
        $unit = $units[$exponent];
        break;
      }
      $bytes /= 1024;
    }

    return round($bytes, 2) . " $unit";
  }
}

if (! function_exists('update_module_file') ) {
  /**
   * Updates the module file.
   *
   * @param array{use: string[], declarations: string[], imports: string[], controllers: string[], providers: string[], exports: string[], config: string[]} $data The data to update the module file with.
   * @param string $filename The name of the module file to update. Defaults to 'AppModule'.
   * @return int Returns a status code.
   */
  function update_module_file(array $data, string $filename = 'AppModule'): int
  {
    $output = new ConsoleOutput();
    $filename = preg_replace('/.php$/', '', $filename);
    $filename = Path::join(getcwd() ?: '', 'src', $filename) . '.php';

    if (! file_exists($filename) ) {
      $output->writeln("<error>File $filename does not exist.</error>");
      return Command::FAILURE;
    }

    # Read the file
    $contents = file_get_contents($filename) ?: throw new RuntimeException("Failed to read file $filename.");
    $originalBytes = strlen($contents);

    # Replace the use statements
    foreach ($data['use'] as $import) {
      if (! str_contains($contents, $import)) {
        $contents = preg_replace('/(use .*;\n)\n/', "$1use $import;\n\n", $contents);
      }
    }

    $modulePropertyNames = ['declarations', 'imports', 'controllers', 'providers', 'exports', 'config'];

    foreach ($modulePropertyNames as $propertyName) {
      if (! array_key_exists($propertyName, $data)) {
        continue;
      }

      $newEntries = $data[$propertyName];
      $matches = [];
      $pattern = "/$propertyName: \[([a-zA-Z0-9:,\s]*)(,?)\]/";

      if (false === preg_match_all($pattern, $contents, $matches)) {
        $output->writeln("<error>Failed to match $propertyName in $filename.</error>");
        return Command::FAILURE;
      }

      if (empty($matches) || empty($matches[0]) || empty($matches[1])) {
        $output->writeln("<error>No matches found for $propertyName in $filename.</error>");
        return Command::FAILURE;
      }

      [$wholeMatch, $currentEntries] = $matches;

      $withNewLines = str_contains($wholeMatch[0], "\n");

      $currentEntries = trim($currentEntries[0]);
      if (str_ends_with($currentEntries, ',')) {
        $currentEntries = substr($currentEntries, 0, -1);
      }

      $currentEntries = explode(',', $currentEntries);

      $replacement = '';
      $tail = $withNewLines ? ",\n" : ',';
      $totalCurrentEntries = count($currentEntries);

      foreach ($currentEntries as $index => $entry) {
        $entry = trim($entry, $tail);
        if (empty($entry)) {
          continue;
        }
        $replacement .= "$entry";

        if ($totalCurrentEntries === $index + 1) {
          $replacement = trim($replacement);
        }

        $replacement .= ',';
      }

      foreach ($newEntries as $entry) {
        if (empty($entry)) {
          continue;
        }

        if (str_contains($replacement, $entry)) {
          continue;
        }

        $prefix = $withNewLines ? "    " : ' ';
        $replacement .= "$prefix$entry,";
      }

      if ($withNewLines) {
        $replacement = str_replace(',', ",\n", $replacement);
      } elseif (str_ends_with($replacement, ',')) {
        $replacement = substr($replacement, 0, -1);
      }

      if ($withNewLines) {
        $replacement = "\n    $replacement  ";
      }

      $contents = preg_replace($pattern, "$propertyName: [$replacement]", $contents);

      if (is_null($contents)) {
        $output->writeln("<error>Failed to replace $propertyName in $filename.</error>");
        return Command::FAILURE;
      }
    }

    # Write the file
    $bytes = file_put_contents($filename, $contents) ?: throw new RuntimeException("Failed to write file $filename.");

    $bytes = format_bytes(abs($bytes - $originalBytes));
    $relativeFilename = str_replace(Path::join((getcwd() ?: ''), 'src') . DIRECTORY_SEPARATOR, '', $filename);

    if ((int)$bytes > 0) {
      $output->writeln("<fg=blue>UPDATE</> $relativeFilename ($bytes)");
    }
    return Command::SUCCESS;
  }
}