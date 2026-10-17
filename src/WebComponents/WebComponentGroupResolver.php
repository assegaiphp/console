<?php

namespace Assegai\Console\WebComponents;

use Assegai\Console\Util\Path;
use InvalidArgumentException;

final class WebComponentGroupResolver
{
  /**
   * @param list<string> $selected
   * @return list<WebComponentGroup>
   */
  public function resolve(string $workspace, array $selected = []): array
  {
    $workspace = realpath($workspace) ?: $workspace;
    $config = WebComponentConfig::load($workspace)['webComponents'] ?? [];
    if (!is_array($config)) {
      throw new InvalidArgumentException('webComponents must be an object.');
    }
    $definitions = $config['groups'] ?? null;
    $cache = WebComponentConfig::getCacheDirectory($workspace);
    $marker = $config['hotReload']['path'] ?? WebComponentConfig::DEFAULT_HOT_RELOAD_PATH;

    if (!array_key_exists('groups', $config)) {
      if ($selected !== []) {
        throw new InvalidArgumentException('No Web Component groups are configured.');
      }
      return [new WebComponentGroup($workspace, null, [Path::join($workspace, 'src')],
        WebComponentConfig::getOutputPath($workspace), $cache, WebComponentConfig::getHotReloadPath($workspace))];
    }

    if (!is_array($definitions) || $definitions === [] || array_is_list($definitions)) {
      throw new InvalidArgumentException('webComponents.groups must be a nonempty object.');
    }
    foreach ($selected as $name) {
      if (!array_key_exists($name, $definitions)) {
        throw new InvalidArgumentException("Unknown Web Component group: $name");
      }
    }

    $groups = [];
    $sources = [];
    if (!is_string($marker)) {
      throw new InvalidArgumentException('The hot reload path must be a workspace-relative path.');
    }
    $artifacts = [self::resolvePath($workspace, $marker)];
    ksort($definitions);
    foreach ($definitions as $name => $definition) {
      if (!is_string($name) || !preg_match('/^[a-z][a-z0-9-]*$/D', $name) || !is_array($definition)
        || array_diff(array_keys($definition), ['sources', 'output']) !== []) {
        throw new InvalidArgumentException('Groups require a lowercase name, sources and output.');
      }
      $inputs = $definition['sources'] ?? null;
      $output = $definition['output'] ?? null;
      if (!is_array($inputs) || !array_is_list($inputs) || $inputs === []
        || !is_string($output) || !str_ends_with($output, '.js') || !is_string($marker)) {
        throw new InvalidArgumentException("Group $name requires source paths and a .js output path.");
      }
      $paths = [];
      foreach ($inputs as $input) {
        if (!is_string($input)) {
          throw new InvalidArgumentException("Invalid source path in group $name.");
        }
        $path = self::resolvePath($workspace, $input);
        if (is_file($path) && !str_ends_with($path, '.wc.ts')) {
          throw new InvalidArgumentException("Group sources must be directories or .wc.ts files: $input");
        }
        foreach ($sources as $existing) {
          if (self::overlaps($path, $existing)) {
            throw new InvalidArgumentException("Overlapping Web Component sources: $input");
          }
        }
        $sources[] = $paths[] = $path;
      }
      sort($paths);
      $outputPath = self::resolvePath($workspace, $output);
      $groupCache = Path::join($cache, 'groups', $name);
      $groupMarker = Path::join(dirname($marker), 'groups', $name, basename($marker));
      $group = new WebComponentGroup($workspace, $name, $paths, Path::normalize($output), $groupCache, $groupMarker);
      foreach ([$outputPath, substr($outputPath, 0, -3) . '.css', $group->entryFilename(),
        $group->manifestFilename(), $group->metafileFilename(), self::resolvePath($workspace, $groupMarker)] as $artifact) {
        $path = self::resolvePath($workspace, ltrim(substr((string) $artifact, strlen($workspace)), '/'));
        foreach ($artifacts as $existing) {
          if (self::overlaps($path, $existing)) {
            throw new InvalidArgumentException("Conflicting Web Component artifact path: $path");
          }
        }
        $artifacts[] = $path;
      }
      $groups[] = $group;
    }
    foreach ($sources as $source) {
      foreach ($artifacts as $artifact) {
        if (self::overlaps($source, $artifact)) {
          throw new InvalidArgumentException('Web Component source and artifact paths overlap.');
        }
      }
    }

    return $selected === [] ? $groups : array_values(array_filter($groups,
      static fn(WebComponentGroup $group): bool => in_array($group->name, $selected, true)));
  }

  public static function resolvePath(string $workspace, string $relative): string
  {
    $relative = str_replace('\\', '/', $relative);
    if ($relative === '' || $relative === '.' || strpbrk($relative, "*?[]\r\n") !== false
      || preg_match('#(^/|^[A-Za-z]:|:|\x00|(^|/)\.\.(/|$))#', $relative)) {
      throw new InvalidArgumentException("Expected a workspace-relative path: $relative");
    }
    $path = Path::join($workspace, $relative);
    $parent = $path;
    while (!file_exists($parent) && !is_link($parent) && dirname($parent) !== $parent) {
      $parent = dirname($parent);
    }
    $canonical = realpath($parent);
    if ($canonical === false || !self::contains($workspace, $canonical)) {
      throw new InvalidArgumentException("Path escapes the workspace: $relative");
    }
    return Path::normalize($canonical . substr($path, strlen($parent)));
  }

  public static function contains(string $directory, string $path): bool
  {
    $directory = rtrim(Path::normalize($directory), '/');
    $path = Path::normalize($path);
    if (PHP_OS_FAMILY === 'Windows') {
      $directory = strtolower($directory);
      $path = strtolower($path);
    }
    return $path === $directory || str_starts_with($path, $directory . '/');
  }

  private static function overlaps(string $left, string $right): bool
  {
    return self::contains($left, $right) || self::contains($right, $left);
  }
}
