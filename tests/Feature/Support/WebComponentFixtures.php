<?php

/**
 * @param array<string, mixed> $webComponentConfig
 * @param array<string, string> $componentFiles
 */
function createWebComponentCommandWorkspace(array $webComponentConfig = [], array $componentFiles = []): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('web-component-command-', true);

  if (!mkdir($workspace . '/src/WebComponents/UI/Alert', 0755, true) && !is_dir($workspace . '/src/WebComponents/UI/Alert')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/composer.json', json_encode([
    'name' => 'assegaiphp/test-workspace',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/bootstrap.php', "<?php\n");
  file_put_contents($workspace . '/assegai.json', json_encode([
    'webComponents' => [...[
      'prefix' => 'app',
      'output' => 'public/js/assegai-components.min.js',
      'buildOnDumpAutoload' => false,
      'hotReload' => [
        'enabled' => true,
        'path' => 'public/.assegai/wc-hot-reload.json',
        'pollInterval' => 1000,
        'ttl' => 43200,
      ],
    ], ...$webComponentConfig],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  $defaultFiles = [
    'src/WebComponents/UI/Alert/AlertComponent.wc.ts' => <<<'TS'
import { defineElement } from '../../runtime';

class AlertElement extends HTMLElement {
}

defineElement('app-alert', AlertElement);
TS,
    'src/WebComponents/UserCard/UserCardComponent.wc.ts' => <<<'TS'
class UserCardElement extends HTMLElement {
}

customElements.define('app-user-card', UserCardElement);
TS,
  ];

  foreach ([...$defaultFiles, ...$componentFiles] as $relativeFilename => $contents) {
    $filename = $workspace . '/' . $relativeFilename;
    $directory = dirname($filename);

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
      throw new RuntimeException("Failed to create directory: $directory");
    }

    file_put_contents($filename, $contents);
  }

  $canonicalWorkspace = realpath($workspace);

  if ($canonicalWorkspace === false) {
    throw new RuntimeException("Failed to canonicalize test workspace: $workspace");
  }

  return $canonicalWorkspace;
}

function deleteWebComponentCommandWorkspace(string $directory): void
{
  if (!is_dir($directory)) {
    return;
  }

  $items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($items as $item) {
    if ($item->isDir()) {
      rmdir($item->getPathname());
      continue;
    }

    unlink($item->getPathname());
  }

  rmdir($directory);
}

function createFakeEsbuildBinary(): string
{
  $binDirectory = sys_get_temp_dir() . '/' . uniqid('fake-esbuild-', true);

  if (!mkdir($binDirectory, 0755, true) && !is_dir($binDirectory)) {
    throw new RuntimeException("Failed to create fake bin directory: $binDirectory");
  }

  $filename = $binDirectory . '/esbuild';
  file_put_contents($filename, <<<'BASH'
#!/usr/bin/env bash
outfile=""

for arg in "$@"; do
  case "$arg" in
    --outfile=*)
      outfile="${arg#--outfile=}"
      ;;
  esac
done

if [ -z "$outfile" ]; then
  exit 1
fi

mkdir -p "$(dirname "$outfile")"
printf '%s\n' '// fake esbuild bundle' > "$outfile"
BASH);
  chmod($filename, 0755);

  return $binDirectory;
}
