<?php

namespace Assegai\Console\WebComponents\Builder;

use Assegai\Console\Util\Path;
use Assegai\Console\WebComponents\Manifest\WebComponentManifest;
use Assegai\Console\WebComponents\Scanner\WebComponentScanner;
use Assegai\Console\WebComponents\Scanner\WebComponentDirectoryIndex;
use Assegai\Console\WebComponents\WebComponentGroup;
use Assegai\Console\WebComponents\WebComponentGroupResolver;
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
   * @param list<string> $selected
   * @return array<int, array{path: string, relativePath: string, tag: string, group?: string}>
   */
  public function discover(string $projectRoot, array $selected = []): array
  {
    return array_merge(...array_column($this->prepare($projectRoot, $selected), 'components'));
  }

  /**
   * @param list<string> $selected
   * @return list<array{group: WebComponentGroup, components: array<int, array{path: string, relativePath: string, tag: string, group?: string}>}>
   */
  public function prepare(string $projectRoot, array $selected = []): array
  {
    $projectRoot = realpath($projectRoot) ?: $projectRoot;
    $this->scanner->directories = [];
    $plan = [];
    foreach ((new WebComponentGroupResolver())->resolve($projectRoot, $selected) as $group) {
      $plan[] = ['group' => $group, 'components' => $this->scanGroup($projectRoot, $group)];
    }
    return $plan;
  }

  /**
   * @param list<array{group: WebComponentGroup, components: array<int, array{path: string, relativePath: string, tag: string, group?: string}>}>|null $plan
   */
  public function build(string $projectRoot, bool $watch = false, ?callable $onWatchTick = null, ?array $plan = null): int
  {
    $projectRoot = realpath($projectRoot) ?: $projectRoot;
    $plan ??= $this->prepare($projectRoot);
    $locks = [];
    try {
      foreach ($plan as $item) {
        $directory = $item['group']->cacheDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
          return Command::FAILURE;
        }
        $lock = fopen(Path::join($directory, 'build.lock'), 'c');
        if ($lock === false) {
          throw new \RuntimeException('Cannot open Web Component build lock.');
        }
        $locks[] = $lock;
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
          throw new \RuntimeException('A selected Web Component group is already being built or watched.');
        }
      }
      if ($plan[0]['group']->name === null) {
        WebComponentConfig::ensureDefaults($projectRoot);
      }
      $jobs = [];
      foreach ($plan as $item) {
        $group = $item['group'];
        if ($this->writeEntryFile($group->entryFilename(), $item['components']) === false) {
          return Command::FAILURE;
        }
        $output = Path::join($projectRoot, $group->output);
        if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0755, true)) {
          return Command::FAILURE;
        }
        if (!$watch && $group->name === null && $item['components'] === []) {
          return $this->writeManifest($item) ? Command::SUCCESS : Command::FAILURE;
        }
        $jobs[] = ['entry' => $group->entryFilename(), 'output' => $output, 'metafile' => $group->metafileFilename()];
        if (!$watch) {
          $status = $this->runner->build($group->entryFilename(), $output, metafile: $group->metafileFilename(),
            workingDirectory: $projectRoot);
          if ($status !== Command::SUCCESS || !$this->writeManifest($item)) {
            return $status === Command::SUCCESS ? Command::FAILURE : $status;
          }
        }
      }
      if (!$watch) {
        return Command::SUCCESS;
      }
      $index = new WebComponentDirectoryIndex(array_keys($this->scanner->directories));
      $ticks = 0;
      $refreshEvery = max(1, (int) ceil(WebComponentConfig::getHotReloadPollInterval($projectRoot) * 1000
        / EsbuildRunner::WATCH_TICK_MICROSECONDS));
      return $this->runner->watchMany($jobs, function (array $completed) use ($projectRoot, &$plan, $index, $onWatchTick, &$ticks, $refreshEvery): mixed {
        $changed = ++$ticks % $refreshEvery === 0 ? $index->refresh() : [];
        if ($changed !== []) {
          foreach ($plan as &$item) {
            foreach ($item['group']->sources as $source) {
              foreach ($changed as $directory) {
                if (WebComponentGroupResolver::contains($source, $directory)
                  || WebComponentGroupResolver::contains($directory, $source)) {
                  $item['components'] = $this->scanGroup($projectRoot, $item['group']);
                  if ($this->writeEntryFile($item['group']->entryFilename(), $item['components']) === false) {
                    throw new \RuntimeException('Failed to update Web Component entry.');
                  }
                  break 2;
                }
              }
            }
          }
          unset($item);
          foreach ($this->scanner->directories as $directory => $_) {
            if (!is_dir($directory)) {
              unset($this->scanner->directories[$directory]);
            }
          }
          $index->includeDirectories(array_keys($this->scanner->directories));
        }
        $versions = [];
        foreach ($completed as $number) {
          $item = $plan[$number];
          if (!$this->writeManifest($item)) {
            continue; // A membership edit arrived while this build was running.
          }
          if ($onWatchTick === null) {
            continue;
          }
          $output = Path::join($projectRoot, $item['group']->output);
          $stylesheet = substr($output, 0, -3) . '.css';
          $versions[$number] = sha1((is_file($output) ? hash_file('sha1', $output) : '')
            . (is_file($stylesheet) ? hash_file('sha1', $stylesheet) : ''));
        }
        return $onWatchTick === null ? null : $onWatchTick($versions);
      }, $projectRoot);
    } finally {
      foreach ($locks as $lock) {
        fclose($lock);
      }
    }
  }

  /** @return array<int, array{path: string, relativePath: string, tag: string, group?: string}> */
  private function scanGroup(string $workspace, WebComponentGroup $group): array
  {
    $components = $this->scanner->scan($workspace, $group->name === null ? null : $group->sources);
    if ($group->name !== null) {
      foreach ($components as &$component) {
        $component['group'] = $group->name;
      }
    }
    return $components;
  }

  /** @param array{group: WebComponentGroup, components: array<int, array{tag: string}>} $item */
  private function writeManifest(array $item): bool
  {
    $metafile = $item['group']->metafileFilename();
    if ($metafile !== null) {
      $metadata = is_file($metafile) ? json_decode(file_get_contents($metafile) ?: '', true) : null;
      if (!is_array($metadata) || !is_array($metadata['inputs'] ?? null) || !is_array($metadata['outputs'] ?? null)) {
        throw new \RuntimeException('esbuild did not produce valid dependency metadata.');
      }
      $workspace = $item['group']->workspace;
      $members = array_column($item['components'], 'path');
      $built = [];
      foreach (array_keys($metadata['inputs']) as $input) {
        if (str_ends_with($input, '.wc.ts')) {
          $path = Path::join($workspace, $input);
          $owned = false;
          foreach ($item['group']->sources as $source) {
            $owned = $owned || WebComponentGroupResolver::contains($source, $path);
          }
          if (!$owned) {
            throw new \RuntimeException("Component dependency is outside group {$item['group']->name}: $input");
          }
          $built[] = $path;
        }
      }
      sort($built);
      sort($members);
      if ($built !== $members) {
        return false;
      }
      $javascript = Path::join($workspace, $item['group']->output);
      $stylesheet = substr($javascript, 0, -3) . '.css';
      $outputs = [];
      foreach (array_keys($metadata['outputs']) as $output) {
        $path = Path::join($workspace, $output);
        if ($path !== $javascript && $path !== $stylesheet) {
          throw new \RuntimeException("Unexpected output in Web Component group: $output");
        }
        $outputs[] = $path;
      }
      if (is_file($stylesheet) && !in_array($stylesheet, $outputs, true) && !unlink($stylesheet)) {
        throw new \RuntimeException('Failed to remove the group stylesheet no longer emitted by esbuild.');
      }
    }
    $filename = $item['group']->manifestFilename();
    $contents = (new WebComponentManifest(array_column($item['components'], 'tag')))->toJson();
    if ((!is_file($filename) || file_get_contents($filename) !== $contents)
      && file_put_contents($filename, $contents) === false) {
      throw new \RuntimeException('Failed to write Web Component manifest.');
    }
    return true;
  }

  /**
   * @param array<int, array{path: string, relativePath: string, tag: string}> $components
   */
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

    return is_file($entryFilename) && file_get_contents($entryFilename) === $contents
      ? 0 : file_put_contents($entryFilename, $contents);
  }

  private function getImportPath(string $entryFilename, string $componentFilename): string
  {
    $entryDirectory = dirname($entryFilename);
    $from = array_values(array_filter(
      explode('/', trim(Path::normalize($entryDirectory), '/')),
      static fn(string $segment): bool => $segment !== '',
    ));
    $to = array_values(array_filter(
      explode('/', trim(Path::normalize($componentFilename), '/')),
      static fn(string $segment): bool => $segment !== '',
    ));

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
