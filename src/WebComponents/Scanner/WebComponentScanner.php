<?php

namespace Assegai\Console\WebComponents\Scanner;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Assegai\Console\WebComponents\WebComponentConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class WebComponentScanner
{
  /** @var array<string, true> Directories visited during discovery, reused by watch. */
  public array $directories = [];

  /**
   * @param list<string>|null $sources
   * @return array<int, array{path: string, relativePath: string, tag: string}>
   */
  public function scan(string $projectRoot, ?array $sources = null): array
  {
    $components = [];
    foreach ($sources ?? [Path::join($projectRoot, 'src')] as $sourceDirectory) {
      if ($sources !== null && is_link($sourceDirectory)) {
        throw new \InvalidArgumentException('Web Component sources cannot become symlinks during discovery.');
      }
      $this->directories[dirname($sourceDirectory)] = true;
      if (!is_dir($sourceDirectory)) {
        $parent = dirname($sourceDirectory);
        while (!is_dir($parent) && dirname($parent) !== $parent) {
          $parent = dirname($parent);
        }
        $this->directories[$parent] = true;
        if (is_file($sourceDirectory) && str_ends_with($sourceDirectory, '.wc.ts')) {
          $components[] = $this->describe($sourceDirectory, $projectRoot);
        }
        continue;
      }
      $this->directories[$sourceDirectory] = true;
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
      );
      foreach ($iterator as $file) {
        if ($sources !== null && $file->isLink()) {
          throw new \InvalidArgumentException('Web Component discovery does not follow source symlinks.');
        }
        if ($file->isDir()) {
          $this->directories[$file->getPathname()] = true;
        }
        if (!$file->isFile()) {
          continue;
        }

        if (!str_ends_with($file->getFilename(), '.wc.ts')) {
          continue;
        }

        $components[] = $this->describe($file->getPathname(), $projectRoot);
      }
    }

    usort($components, fn(array $left, array $right): int => strcmp($left['relativePath'], $right['relativePath']));

    return $components;
  }

  /** @return array{path: string, relativePath: string, tag: string} */
  private function describe(string $path, string $projectRoot): array
  {
    $path = Path::normalize($path);
    return ['path' => $path, 'relativePath' => ltrim(substr($path, strlen($projectRoot)), '/'),
      'tag' => $this->extractTagName($path, $projectRoot)];
  }

  private function extractTagName(string $filename, string $projectRoot): string
  {
    $contents = file_get_contents($filename) ?: '';

    if (preg_match('/(?:defineElement|customElements\\.define)\\(\\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches) === 1) {
      return $matches[1];
    }

    $basename = basename($filename, '.wc.ts');
    $basename = preg_replace('/Component$/', '', $basename) ?: $basename;

    return WebComponentConfig::getPrefix($projectRoot) . '-' . (new Text($basename))->kebabCase();
  }
}
