<?php

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
  if (!is_dir($destination))
  {
    mkdir($destination, 0755, true);
  }
  while (false !== ($entry = $directory->read()))
  {
    if ($entry == '.' || $entry == '..')
    {
      continue;
    }
    if (is_dir($source . '/' . $entry))
    {
      copy_directory($source . '/' . $entry, $destination . '/' . $entry);
      continue;
    }
    if (false === copy($source . '/' . $entry, $destination . '/' . $entry))
    {
      return false;
    }
  }
  $directory->close();

  return true;
}

/**
 * Converts an array to a string.
 *
 * @param array $array The array to convert.
 * @return false|string The string representation of the array.
 */
function array_to_string(array $array): false|string
{
  $output = json_encode($array, JSON_PRETTY_PRINT);

  if (false === $output)
  {
    return false;
  }

  $output = str_replace('{', '[', $output);
  $output = str_replace('}', ']', $output);
  return str_replace('":', '" =>', $output);
}

/**
 * Formats a block of text.
 *
 * @param string $message The message to format.
 * @param string $style The style to apply.
 * @return array The formatted block.
 */
function format_block(string $message, string $style): array
{
  $output = [];
  $lines = explode("\n", $message);
  $longestLine = strlen($message);

  foreach ($lines as $line)
  {
    $longestLine = max(strlen($line), $longestLine);
  }

  foreach ($lines as $index => $line)
  {
    // TODO: Implement the rest of the function
  }

  return $output;
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