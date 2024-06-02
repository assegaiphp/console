<?php

namespace Assegai\Console\Util;

use Assegai\Console\Util\Exceptions\UtilityException;

class Path
{
  /**
   * @var string $workingDirectory The working directory.
   */
  private static string $workingDirectory = '';

  /**
   * Path constructor.
   */
  private function __construct() { }

  /**
   * Joins the given paths.
   *
   * @param string ...$paths The paths to join.
   * @return string
   */
  public static function join(string ...$paths): string
  {
    $result = '';

    foreach ($paths as $path)
    {
      $result .= $path . DIRECTORY_SEPARATOR;
    }

    return self::normalize(rtrim($result, DIRECTORY_SEPARATOR));
  }

  /**
   * Returns the path to the resources' directory.
   *
   * @param string $path The path to the resource.
   * @return string The path to the resource.
   */
  public static function getResourcePath(string $path = ''): string
  {
    if ($path)
    {
      return self::join(self::getProjectRootPath(), 'res', $path);
    }

    return self::join(self::getProjectRootPath(), 'res');
  }

  public static function getServerRoot(): string
  {
    return $_SERVER['DOCUMENT_ROOT'];
  }

  /**
   * Returns the path to the project's root directory.
   *
   * @return string The path to the project's root directory.
   */
  public static function getProjectRootPath(): string
  {
    $path = getcwd();
    echo $path . PHP_EOL;

    while(strlen($path) > 1)
    {
      if (
        file_exists(Path::join($path, 'composer.json')) &&
        file_exists(Path::join($path, 'assegai.json'))
      )
      {
        return $path;
      }
      $path = dirname($path);
    }

    return $path;
  }

  /**
   * Returns the working directory.
   *
   * @return string The working directory.
   */
  public static function getWorkingDirectory(): string
  {
    return getcwd();
  }

  /**
   * Returns the path the assets' directory.
   *
   * @return string The path to the assets' directory.
   */
  public static function getAssetsDirectory(): string
  {
    return self::join(self::getProjectRootPath(), 'assets');
  }

  /**
   * Normalizes the given path.
   *
   * @param string $path The path to normalize.
   * @return string The normalized path.
   */
  public static function normalize(string $path): string
  {
    // Replace backslashes with forward slashes
    $path = str_replace('\\', '/', $path);

    // Explode the path into segments
    $segments = explode('/', $path);

    // Initialize an array to hold normalized segments
    $normalizedSegments = [];

    foreach ($segments as $segment)
    {
      if ($segment === '..')
      {
        // If the segment is '..', pop the last segment from the array
        array_pop($normalizedSegments);
      }
      elseif ($segment !== '' && $segment !== '.')
      {
        // If the segment is not empty and not '.', add it to the array
        $normalizedSegments[] = $segment;
      }
    }

    // Recombine the normalized segments into a path string
    $normalizedPath = implode('/', $normalizedSegments);

    // Determine if the path was originally absolute or relative and prepend accordingly
    $isAbsolute = $path[0] === '/';
    if ($isAbsolute) {
      $normalizedPath = '/' . $normalizedPath;
    }

    return $normalizedPath;
  }

  /**
   * Returns the path to the templates' directory.
   *
   * @return string The path to the templates' directory.
   */
  public static function getTemplatesDirectory(): string
  {
    return Path::join(dirname(__DIR__, 2), 'templates');
  }

  /**
   * Returns the path to the certificates' directory.
   *
   * @return string The path to the certificates' directory.
   */
  public static function getCertificatesDirectory(): string
  {
    return Path::join(dirname(__DIR__, 2), 'certs');
  }
}