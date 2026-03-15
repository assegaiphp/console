<?php

namespace Assegai\Console\WebComponents\Scanner;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Assegai\Console\WebComponents\WebComponentConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class WebComponentScanner
{
  /**
   * @return array<int, array{path: string, relativePath: string, tag: string}>
   */
  public function scan(string $projectRoot): array
  {
    $sourceDirectory = Path::join($projectRoot, 'src');

    if (!is_dir($sourceDirectory)) {
      return [];
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($sourceDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    $components = [];

    foreach ($iterator as $file) {
      if (!$file->isFile()) {
        continue;
      }

      if (!str_ends_with($file->getFilename(), '.wc.ts')) {
        continue;
      }

      $absolutePath = Path::normalize($file->getPathname());
      $components[] = [
        'path' => $absolutePath,
        'relativePath' => ltrim(str_replace($projectRoot, '', $absolutePath), DIRECTORY_SEPARATOR),
        'tag' => $this->extractTagName($absolutePath, $projectRoot),
      ];
    }

    usort($components, fn(array $left, array $right): int => strcmp($left['relativePath'], $right['relativePath']));

    return $components;
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
