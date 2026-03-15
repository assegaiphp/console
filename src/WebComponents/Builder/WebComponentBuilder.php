<?php

namespace Assegai\Console\WebComponents\Builder;

use Assegai\Console\Util\Path;
use Assegai\Console\WebComponents\Manifest\WebComponentManifest;
use Assegai\Console\WebComponents\Scanner\WebComponentScanner;
use Assegai\Console\WebComponents\WebComponentConfig;
use Symfony\Component\Console\Command\Command;

final class WebComponentBuilder
{
  public function __construct(
    private readonly WebComponentScanner $scanner = new WebComponentScanner(),
    private readonly EsbuildRunner $runner = new EsbuildRunner()
  )
  {
  }

  /**
   * @return array<int, array{path: string, relativePath: string, tag: string}>
   */
  public function discover(string $projectRoot): array
  {
    return $this->scanner->scan($projectRoot);
  }

  public function build(string $projectRoot, bool $watch = false): int
  {
    WebComponentConfig::ensureDefaults($projectRoot);

    $components = $this->discover($projectRoot);
    $entryFilename = WebComponentConfig::getEntryFilename($projectRoot);
    $manifestFilename = WebComponentConfig::getManifestFilename($projectRoot);
    $outputFilename = Path::join($projectRoot, WebComponentConfig::getOutputPath($projectRoot));

    if (false === $this->writeEntryFile($entryFilename, $components)) {
      return Command::FAILURE;
    }

    if (false === (new WebComponentManifest(array_column($components, 'tag')))->write($manifestFilename)) {
      return Command::FAILURE;
    }

    if (empty($components)) {
      return Command::SUCCESS;
    }

    $outputDirectory = dirname($outputFilename);

    if (!is_dir($outputDirectory) && false === mkdir($outputDirectory, 0755, true)) {
      return Command::FAILURE;
    }

    if (!$this->runner->isAvailable()) {
      return Command::FAILURE;
    }

    return $this->runner->build($entryFilename, $outputFilename, $watch);
  }

  private function writeEntryFile(string $entryFilename, array $components): int|false
  {
    $directory = dirname($entryFilename);

    if (!is_dir($directory) && false === mkdir($directory, 0755, true)) {
      return false;
    }

    $contents = empty($components)
      ? "// No Web Components discovered.\n"
      : implode("\n", array_map(
        fn(array $component): string => "import '" . $this->getImportPath($entryFilename, $component['path']) . "';",
        $components
      )) . "\n";

    return file_put_contents($entryFilename, $contents);
  }

  private function getImportPath(string $entryFilename, string $componentFilename): string
  {
    $entryDirectory = dirname($entryFilename);
    $from = array_values(array_filter(explode('/', trim(Path::normalize($entryDirectory), '/')), 'strlen'));
    $to = array_values(array_filter(explode('/', trim(Path::normalize($componentFilename), '/')), 'strlen'));

    while (!empty($from) && !empty($to) && $from[0] === $to[0]) {
      array_shift($from);
      array_shift($to);
    }

    $relativePath = implode('/', array_merge(array_fill(0, count($from), '..'), $to));

    if ($relativePath === '') {
      return './' . basename($componentFilename);
    }

    if (!str_starts_with($relativePath, '.')) {
      $relativePath = './' . $relativePath;
    }

    return $relativePath;
  }
}
