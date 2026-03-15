<?php

use Assegai\Console\Commands\DumpAutoload;
use Assegai\Console\Commands\WebComponents\BuildWebComponents;
use Assegai\Console\Commands\WebComponents\ListWebComponents;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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

  return $workspace;
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

describe('Web Component commands', function () {
  it('builds the discovered Web Components into a single bundle', function () {
    $workspace = createWebComponentCommandWorkspace();
    $binDirectory = createFakeEsbuildBinary();
    $previousWorkingDirectory = getcwd();
    $previousPath = getenv('PATH') ?: '';

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);
    putenv('PATH=' . $binDirectory . PATH_SEPARATOR . $previousPath);

    try {
      $commandTester = new CommandTester(new BuildWebComponents());
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($workspace . '/.cache/assegai/wc-entry.ts')->toBeFile();
      expect($workspace . '/.cache/assegai/web-components.manifest.json')->toBeFile();
      expect($workspace . '/public/js/assegai-components.min.js')->toBeFile();
      expect(file_get_contents($workspace . '/.cache/assegai/wc-entry.ts'))
        ->toContain("import '../../src/WebComponents/UI/Alert/AlertComponent.wc.ts';")
        ->toContain("import '../../src/WebComponents/UserCard/UserCardComponent.wc.ts';");
      expect(file_get_contents($workspace . '/.cache/assegai/web-components.manifest.json'))
        ->toContain('"app-alert"')
        ->toContain('"app-user-card"');
      expect($commandTester->getDisplay())->toContain('Built')->toContain('public/js/assegai-components.min.js');
    } finally {
      putenv('PATH=' . $previousPath);
      chdir($previousWorkingDirectory);
      deleteWebComponentCommandWorkspace($workspace);
      deleteWebComponentCommandWorkspace($binDirectory);
    }
  });

  it('lists the discovered Web Components', function () {
    $workspace = createWebComponentCommandWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new ListWebComponents());
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($commandTester->getDisplay())
        ->toContain('app-alert')
        ->toContain('src/WebComponents/UI/Alert/AlertComponent.wc.ts')
        ->toContain('app-user-card');
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentCommandWorkspace($workspace);
    }
  });

  it('runs the Web Component build hook during dump-autoload when enabled', function () {
    $workspace = createWebComponentCommandWorkspace(['buildOnDumpAutoload' => true]);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $command = new class extends DumpAutoload {
        public array $calls = [];

        protected function runComposerDumpAutoload(): int
        {
          $this->calls[] = 'composer';
          return Command::SUCCESS;
        }

        protected function buildWebComponents(string $workspace): int
        {
          $this->calls[] = "build:$workspace";
          return Command::SUCCESS;
        }
      };

      $commandTester = new CommandTester($command);
      $status = $commandTester->execute([]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toBe([
        'composer',
        'build:' . $workspace,
      ]);
      expect($commandTester->getDisplay())->toContain('Building Web Components');
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentCommandWorkspace($workspace);
    }
  });
});
