<?php

namespace Assegai\Console\Util;

/**
 * The Path class provides utility methods for working with file paths.
 *
 * @package Assegai\Console\Util
 */
class Path
{
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
   * @return false|string The path to the resource. False if the project root path could not be determined.
   */
  public static function getResourcePath(string $path = ''): false|string
  {
    $rootPath = self::getProjectRootPath();

    if (false === $rootPath)
    {
      return false;
    }

    if ($path)
    {
      return self::join($rootPath, 'res', $path);
    }

    return self::join($rootPath, 'res');
  }

  /**
   * Returns the path to the public directory.
   *
   * @return string The path to the public directory.
   */
  public static function getServerRoot(): string
  {
    return $_SERVER['DOCUMENT_ROOT'];
  }

  /**
   * Returns the path to the project's root directory.
   *
   * @return false|string The path to the project's root directory. False if the project root path could not be determined.
   */
  public static function getProjectRootPath(): false|string
  {
    $path = getcwd();

    if (false === $path)
    {
      return false;
    }

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
   * @return false|string The working directory. False if the working directory could not be determined.
   */
  public static function getWorkingDirectory(): false|string
  {
    return getcwd();
  }

  /**
   * Returns the path the assets' directory.
   *
   * @return false|string The path to the assets' directory. False if the project root path could not be determined.
   */
  public static function getAssetsDirectory(): false|string
  {
    $rootPath = self::getProjectRootPath();

    if (false === $rootPath)
    {
      return false;
    }

    return self::join($rootPath, 'assets');
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

  /**
   * Returns the path to the migrations' directory.
   *
   * @return string The path to the migrations' directory.
   */
  public static function getMigrationsDirectory(): string
  {
    return Path::join(self::getWorkingDirectory() ?: '', 'migrations');
  }
}