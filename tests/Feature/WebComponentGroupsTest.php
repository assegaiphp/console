<?php

use Assegai\Console\Commands\WebComponents\BuildWebComponents;
use Assegai\Console\Commands\WebComponents\ListWebComponents;
use Assegai\Console\WebComponents\Builder\EsbuildRunner;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Assegai\Console\WebComponents\Scanner\WebComponentDirectoryIndex;
use Assegai\Console\WebComponents\WebComponentGroupResolver;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/Support/WebComponentFixtures.php';

/** @return array<string, array{sources: list<string>, output: string}> */
function componentGroups(): array
{
  return [
    'site' => ['sources' => ['src/WebComponents/UI'], 'output' => 'public/js/site.min.js'],
    'sandbox' => ['sources' => ['src/WebComponents/UserCard'], 'output' => 'public/js/sandbox.min.js'],
  ];
}

// A small process fixture exercises Console orchestration, not TypeScript compilation.
function groupEsbuildFixture(string $workspace): string
{
  $filename = $workspace . '/fake-esbuild.php';
  file_put_contents($filename, <<<'PHP'
#!/usr/bin/env php
<?php
$entry = $argv[1];
$output = $metafile = null;
foreach ($argv as $argument) {
  if (str_starts_with($argument, '--outfile=')) { $output = substr($argument, 10); }
  if (str_starts_with($argument, '--metafile=')) { $metafile = substr($argument, 11); }
}
$watch = in_array('--watch', $argv, true);
if (str_contains($output, 'fail.js')) { exit(2); }
$previous = null;
$previousError = null;
if ($watch) { file_put_contents($output . '.pid', (string) getmypid()); }
stream_set_blocking(STDIN, false);
do {
  clearstatcache(true);
  $contents = file_get_contents($entry);
  preg_match_all("/import '([^']+)';/", $contents, $matches);
  $inputs = [$entry => ['bytes' => strlen($contents)]];
  $invalid = false;
  foreach ($matches[1] as $relative) {
    $source = realpath(dirname($entry) . '/' . $relative);
    if ($source === false || !is_file($source)) { $invalid = true; break; }
    $value = file_get_contents($source);
    $contents .= $value;
    $inputs[substr($source, strlen(getcwd()) + 1)] = ['bytes' => strlen($value)];
  }
  if ($invalid) {
    if (!$watch) { exit(1); }
    if ($previousError !== $contents) { fwrite(STDERR, "[ERROR] missing fixture input\n[watch] build finished\n"); }
    $previousError = $contents;
    usleep(50000);
    continue;
  }
  $stylesheet = getcwd() . '/src/shared.css';
  $css = is_file($stylesheet) ? file_get_contents($stylesheet) : null;
  $hash = sha1($contents . ($css ?? ''));
  if ($hash !== $previous) {
    file_put_contents($output, $contents);
    file_put_contents($output . '.calls', "build\n", FILE_APPEND);
    $outputs = [substr($output, strlen(getcwd()) + 1) => ['bytes' => strlen($contents)]];
    if ($css !== null) {
      $cssOutput = substr($output, 0, -3) . '.css';
      file_put_contents($cssOutput, $css);
      $outputs[substr($cssOutput, strlen(getcwd()) + 1)] = ['bytes' => strlen($css)];
      $inputs['src/shared.css'] = ['bytes' => strlen($css)];
    }
    if ($metafile !== null) {
      file_put_contents($metafile, json_encode(['inputs' => $inputs, 'outputs' => $outputs]));
    }
    if ($watch) { fwrite(STDERR, "[watch] build finished, watching for changes...\n"); }
    $previous = $hash;
  }
  if (!$watch || feof(STDIN)) { break; }
  usleep(50000);
} while (true);
PHP);
  chmod($filename, 0755);
  return $filename;
}

it('builds and lists explicit groups while preserving every unselected artifact', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  $previousPath = getenv('PATH') ?: '';
  try {
    mkdir($workspace . '/bin');
    $fake = groupEsbuildFixture($workspace);
    symlink($fake, $workspace . '/bin/esbuild');
    putenv('PATH=' . $workspace . '/bin' . PATH_SEPARATOR . $previousPath);
    $builder = new WebComponentBuilder();
    $plan = $builder->prepare($workspace);
    expect($builder->build($workspace, plan: $plan))->toBe(0);
    $sandboxOutput = $workspace . '/public/js/sandbox.min.js';
    $sandboxMeta = $workspace . '/.cache/assegai/groups/sandbox/esbuild-meta.json';
    $snapshot = [file_get_contents($sandboxOutput), file_get_contents($sandboxMeta), filemtime($sandboxMeta)];
    file_put_contents($workspace . '/src/WebComponents/UI/Alert/AlertComponent.wc.ts', 'class UpdatedNavbar {}');
    $command = new CommandTester(new BuildWebComponents());
    expect($command->execute(['--directory' => $workspace, '--group' => ['site']]))->toBe(0);
    expect([file_get_contents($sandboxOutput), file_get_contents($sandboxMeta), filemtime($sandboxMeta)])->toBe($snapshot);
    expect(file_get_contents($workspace . '/public/js/site.min.js'))->toContain('UpdatedNavbar');
    $list = new CommandTester(new ListWebComponents());
    expect($list->execute(['--directory' => $workspace, '--group' => ['site'], '--json' => true]))->toBe(0);
    $components = json_decode($list->getDisplay(), true);
    expect(array_column($components, 'group'))->toBe(['site']);
    expect(array_column($components, 'relativePath'))->toBe(['src/WebComponents/UI/Alert/AlertComponent.wc.ts']);
    $meta = json_decode(file_get_contents($workspace . '/.cache/assegai/groups/site/esbuild-meta.json') ?: '{}', true);
    expect($meta['inputs'])->toHaveKey('src/WebComponents/UI/Alert/AlertComponent.wc.ts');
    expect($workspace . '/.cache/assegai/wc-entry.ts')->not->toBeFile();
  } finally {
    putenv('PATH=' . $previousPath);
    deleteWebComponentCommandWorkspace($workspace);
  }
});

it('coordinates aggregate reload and clean termination with hot reload enabled or disabled', function (bool $hotReload) {
  if (!function_exists('pcntl_signal')) {
    throw new \PHPUnit\Framework\SkippedWithMessageException('Signal handling requires pcntl.');
  }
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  $process = null;
  $pipes = [];
  try {
    mkdir($workspace . '/bin');
    symlink(groupEsbuildFixture($workspace), $workspace . '/bin/esbuild');
    $environment = getenv();
    $environment['PATH'] = $workspace . '/bin' . PATH_SEPARATOR . ($environment['PATH'] ?? '');
    $environment['ASSEGAI_NO_UPDATE_CHECK'] = '1';
    $arguments = [PHP_BINARY, '-d', 'memory_limit=128M', dirname(__DIR__, 2) . '/bin/assegai', 'wc:watch', '--directory', $workspace];
    if (!$hotReload) { $arguments[] = '--no-hot-reload'; }
    $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
      $pipes, $workspace, $environment);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start watch command.'); }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $rootMarker = $workspace . '/public/.assegai/wc-hot-reload.json';
    $deadline = microtime(true) + 5;
    $original = null;
    $updated = false;
    do {
      stream_get_contents($pipes[1]);
      stream_get_contents($pipes[2]);
      $marker = is_file($rootMarker) ? json_decode(file_get_contents($rootMarker) ?: '{}', true) : [];
      $ready = count(glob($workspace . '/.cache/assegai/groups/*/web-components.manifest.json') ?: []) === 2;
      if ($ready && !$hotReload) { $updated = true; break; }
      if ($ready && $original === null) {
        $original = $marker['version'] ?? null;
        $file = $workspace . '/src/WebComponents/UserCard/UserCardComponent.wc.ts';
        $timestamp = (int) filemtime($file);
        file_put_contents($file, str_replace('UserCardElement', 'UserTileElement', file_get_contents($file) ?: ''));
        touch($file, $timestamp); // Same byte count and timestamp must still reload.
      } elseif ($original !== null && ($marker['version'] ?? null) !== $original) {
        $updated = true;
        break;
      }
      usleep(20000);
    } while (microtime(true) < $deadline);
    expect($updated)->toBeTrue();
    proc_terminate($process);
    $deadline = microtime(true) + 2;
    do {
      $status = proc_get_status($process);
      if (!$status['running']) { break; }
      usleep(20000);
    } while (microtime(true) < $deadline);
    expect($status['running'])->toBeFalse();
    foreach ([$rootMarker, $workspace . '/public/.assegai/groups/site/wc-hot-reload.json',
      $workspace . '/public/.assegai/groups/sandbox/wc-hot-reload.json'] as $marker) {
      if ($hotReload) {
        $state = json_decode(file_get_contents($marker) ?: '{}', true);
        expect($state['active'])->toBeFalse();
      } else {
        expect(is_file($marker))->toBeFalse();
      }
    }
  } finally {
    if (is_resource($process)) {
      if (proc_get_status($process)['running']) { proc_terminate($process, 9); }
      proc_close($process);
      foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    }
    deleteWebComponentCommandWorkspace($workspace);
  }
})->with([true, false]);

it('propagates unknown group failures through the real CLI entrypoint', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  try {
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/assegai', 'wc:list', '--group', 'missing',
      '--directory', $workspace], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workspace,
      ['ASSEGAI_NO_UPDATE_CHECK' => '1']);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start CLI command.'); }
    $output = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($process))->toBe(1);
    expect($output)->toContain('Unknown Web Component group: missing');
    expect(is_dir($workspace . '/.cache'))->toBeFalse();
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
});

it('uses the executing CLI when a workspace autoloader contains an older Console', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  try {
    mkdir($workspace . '/vendor');
    mkdir($workspace . '/old-console/WebComponents/Builder', 0755, true);
    mkdir($workspace . '/old-console/Core/Packages', 0755, true);
    mkdir($workspace . '/vendor/example/extension', 0755, true);
    file_put_contents($workspace . '/old-console/WebComponents/Builder/WebComponentBuilder.php',
      '<?php namespace Assegai\\Console\\WebComponents\\Builder; final class WebComponentBuilder {}');
    file_put_contents($workspace . '/old-console/Core/Packages/InstalledPackageExtension.php',
      '<?php namespace Assegai\\Console\\Core\\Packages; class InstalledPackageExtension { public function __construct() { throw new \\RuntimeException("Loaded old Console extension class"); } }');
    file_put_contents($workspace . '/vendor/example/extension/composer.json', json_encode([
      'name' => 'example/extension', 'extra' => ['assegai' => ['aliases' => ['fixture']]],
    ]));
    file_put_contents($workspace . '/vendor/autoload.php', <<<'PHP'
<?php
$loader = new Composer\Autoload\ClassLoader();
$loader->setPsr4('Assegai\\Console\\', dirname(__DIR__) . '/old-console');
$loader->register(true);
return $loader;
PHP);
    $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/assegai', 'wc:list', '--group', 'site',
      '--json', '--directory', $workspace], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workspace,
      ['ASSEGAI_NO_UPDATE_CHECK' => '1']);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start CLI command.'); }
    $output = stream_get_contents($pipes[1]) ?: '';
    $error = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    expect(proc_close($process))->toBe(0, $error);
    expect(array_column(json_decode($output, true), 'group'))->toBe(['site']);
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
});

it('rejects invalid group ownership before writing artifacts', function (string $case) {
  $groups = componentGroups();
  if ($case === 'overlap') { $groups['site']['sources'] = ['src/WebComponents']; }
  if ($case === 'same-output') { $groups['site']['output'] = $groups['sandbox']['output']; }
  if ($case === 'traversal') { $groups['site']['output'] = '../escaped.js'; }
  if ($case === 'source-output') { $groups['site']['output'] = 'src/WebComponents/UI/site.js'; }
  if ($case === 'unknown-key') { $groups['site']['entry'] = 'something.ts'; }
  $workspace = createWebComponentCommandWorkspace(['groups' => $groups]);
  try {
    $command = new CommandTester(new BuildWebComponents());
    $selected = $case === 'unknown-group' ? ['missing'] : ['site'];
    expect($command->execute(['--directory' => $workspace, '--group' => $selected]))->toBe(1);
    expect($workspace . '/.cache')->not->toBeDirectory();
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
})->with(['overlap', 'same-output', 'traversal', 'source-output', 'unknown-key', 'unknown-group']);

it('tracks topology independently of content and timestamps with bounded directory reads', function () {
  $workspace = createWebComponentCommandWorkspace();
  $directory = $workspace . '/src/WebComponents/UI/Alert';
  try {
    $missing = new WebComponentDirectoryIndex([$directory . '/Missing']);
    expect($missing->refresh())->toBe([]);
    expect($missing->listCount)->toBe(1);
    $index = new WebComponentDirectoryIndex([$directory]);
    $file = $directory . '/AlertComponent.wc.ts';
    $timestamp = (int) filemtime($file);
    file_put_contents($file, 'different content');
    touch($file, $timestamp);
    expect($index->refresh())->toBe([]);
    rename($file, $directory . '/Renamed.wc.ts');
    $changed = $index->refresh();
    expect($changed)->toContain($file, $directory . '/Renamed.wc.ts');
    expect($index->listCount)->toBe(3);
    $child = $directory . '/Child';
    mkdir($child);
    $index->refresh();
    $index->includeDirectories([$child]);
    rmdir($child);
    expect($index->refresh())->toContain($child);
    $index->includeDirectories([$child]);
    $before = $index->listCount;
    expect($index->refresh())->toBe([]);
    expect($index->listCount - $before)->toBe(1);
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
});

it('watches the first component in a future directory and handles removal back to empty', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => [
    'future' => ['sources' => ['src/Future'], 'output' => 'public/js/future.js'],
  ]]);
  try {
    $builder = new WebComponentBuilder(runner: new EsbuildRunner(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(groupEsbuildFixture($workspace))));
    $plan = $builder->prepare($workspace);
    $phase = 0;
    $deadline = microtime(true) + 5;
    $status = $builder->build($workspace, true, function (array $versions) use ($workspace, &$phase, $deadline): ?bool {
      if (microtime(true) > $deadline) { throw new RuntimeException('Empty-group watch timed out.'); }
      if ($versions === []) { return null; }
      $manifest = file_get_contents($workspace . '/.cache/assegai/groups/future/web-components.manifest.json') ?: '';
      if ($phase === 0) {
        mkdir($workspace . '/src/Future');
        file_put_contents($workspace . '/src/Future/First.wc.ts', 'class First {}');
        $phase++;
      } elseif ($phase === 1 && str_contains($manifest, 'app-first')) {
        unlink($workspace . '/src/Future/First.wc.ts');
        rmdir($workspace . '/src/Future');
        $phase++;
      } elseif ($phase === 2 && str_contains($manifest, '"components": []')) {
        $phase++;
        return false;
      }
      return null;
    }, $plan);
    expect($status)->toBe(0);
    expect($phase)->toBe(3);
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
});

it('versions CSS-only rebuilds and removes only the group stylesheet no longer emitted', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  try {
    $style = $workspace . '/src/shared.css';
    file_put_contents($style, 'red');
    $builder = new WebComponentBuilder(runner: new EsbuildRunner(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(groupEsbuildFixture($workspace))));
    $phase = 0;
    $firstVersion = $javascript = '';
    $deadline = microtime(true) + 4;
    $status = $builder->build($workspace, true, function (array $versions) use ($workspace, $style, &$phase, &$firstVersion, &$javascript, $deadline): ?bool {
      if (microtime(true) > $deadline) { throw new RuntimeException('CSS watch timed out.'); }
      if ($versions === []) { return null; }
      if ($phase === 0) {
        $firstVersion = $versions[0];
        $javascript = file_get_contents($workspace . '/public/js/site.min.js');
        file_put_contents($style, 'tan');
        $phase++;
      } elseif ($phase === 1 && $versions[0] !== $firstVersion) {
        expect(file_get_contents($workspace . '/public/js/site.min.js'))->toBe($javascript);
        unlink($style);
        $phase++;
      } elseif ($phase === 2 && !is_file($workspace . '/public/js/site.min.css')) {
        $phase++;
        return false;
      }
      return null;
    }, $builder->prepare($workspace, ['site']));
    expect($status)->toBe(0);
    expect($phase)->toBe(3);
    expect(is_file($workspace . '/public/js/sandbox.min.js'))->toBeFalse();
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
});

it('rejects escaping source/output symlinks and preserves case-sensitive containment', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  $outside = createWebComponentCommandWorkspace();
  $link = $workspace . '/escape';
  try {
    if (PHP_OS_FAMILY !== 'Windows') {
      expect(WebComponentGroupResolver::contains('/tmp/App', '/tmp/app/entry.wc.ts'))->toBeFalse();
    }
    expect(symlink($outside, $link))->toBeTrue();
    expect(fn() => WebComponentGroupResolver::resolvePath($workspace, 'escape/public/site.js'))
      ->toThrow(InvalidArgumentException::class);
    $groups = componentGroups();
    $groups['site']['sources'] = ['escape/src'];
    file_put_contents($workspace . '/assegai.json', json_encode(['webComponents' => ['groups' => $groups]]));
    expect(fn() => (new WebComponentGroupResolver())->resolve($workspace))->toThrow(InvalidArgumentException::class);
  } finally {
    if (is_link($link)) { unlink($link); }
    deleteWebComponentCommandWorkspace($workspace);
    deleteWebComponentCommandWorkspace($outside);
  }
});

it('rediscovers additions removals and renames and stops every selected watch process', function () {
  $workspace = createWebComponentCommandWorkspace(['groups' => componentGroups()]);
  try {
    $fake = groupEsbuildFixture($workspace);
    $builder = new WebComponentBuilder(runner: new EsbuildRunner(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fake)));
    $plan = $builder->prepare($workspace);
    $phase = 0;
    $deadline = microtime(true) + 5;
    $alert = $workspace . '/src/WebComponents/UI/Alert/AlertComponent.wc.ts';
    $new = dirname($alert) . '/New.wc.ts';
    $renamed = dirname($alert) . '/Renamed.wc.ts';
    $status = $builder->build($workspace, true, function (array $versions) use (&$phase, $deadline, $workspace, $alert, $new, $renamed): ?bool {
      if (microtime(true) > $deadline) { throw new RuntimeException('Watch fixture timed out.'); }
      if ($versions === []) { return null; }
      $entry = file_get_contents($workspace . '/.cache/assegai/groups/site/wc-entry.ts') ?: '';
      if ($phase === 0 && count(glob($workspace . '/public/js/*.pid') ?: []) === 2) {
        file_put_contents($new, "customElements.define('app-new', class extends HTMLElement {});");
        $phase++;
      } elseif ($phase === 1 && str_contains($entry, 'New.wc.ts')) {
        rename($new, $renamed);
        unlink($alert);
        $phase++;
      } elseif ($phase === 2 && str_contains($entry, 'Renamed.wc.ts') && !str_contains($entry, 'AlertComponent.wc.ts')) {
        $phase++;
        return false;
      }
      return null;
    }, $plan);
    expect($status)->toBe(0);
    expect($phase)->toBe(3);
    if (function_exists('posix_kill')) {
      foreach (glob($workspace . '/public/js/*.pid') ?: [] as $filename) {
        expect(posix_kill((int) file_get_contents($filename), 0))->toBeFalse();
      }
    }
  } finally {
    deleteWebComponentCommandWorkspace($workspace);
  }
});
