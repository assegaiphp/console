<?php

use Assegai\Console\Commands\DumpAutoload;
use Assegai\Console\Commands\WebComponents\BuildWebComponents;
use Assegai\Console\Commands\WebComponents\ListWebComponents;
use Assegai\Console\Commands\WebComponents\WatchWebComponents;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Assegai\Console\WebComponents\HotReload\WebComponentHotReloadState;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

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

  it('lists the discovered Web Components with aligned highlighted tags', function () {
    $workspace = createWebComponentCommandWorkspace([], [
      'src/WebComponents/Layout/Drawer/DrawerComponent.wc.ts' => <<<'TS'
class DrawerContentElement extends HTMLElement {
}

customElements.define('app-drawer-content', DrawerContentElement);
TS,
    ]);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new ListWebComponents());
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ], ['decorated' => true]);

      expect($status)->toBe(Command::SUCCESS);

      $display = $commandTester->getDisplay();
      $tagColumnWidth = 20;

      expect($display)
        ->toContain(sprintf("\e[33m%s\e[39m", str_pad('app-alert', $tagColumnWidth)))
        ->toContain(sprintf("\e[33m%s\e[39m", str_pad('app-drawer-content', $tagColumnWidth)))
        ->toContain(sprintf("\e[33m%s\e[39m", str_pad('app-user-card', $tagColumnWidth)))
        ->toContain('src/WebComponents/UI/Alert/AlertComponent.wc.ts')
        ->toContain('src/WebComponents/Layout/Drawer/DrawerComponent.wc.ts')
        ->toContain('src/WebComponents/UserCard/UserCardComponent.wc.ts');
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
        /** @var array<int, mixed> */
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

  it('writes and deactivates the hot reload state file', function () {
    $workspace = createWebComponentCommandWorkspace();
    $state = new WebComponentHotReloadState($workspace);
    $filename = $workspace . '/public/.assegai/wc-hot-reload.json';
    $bundleFilename = $workspace . '/public/js/assegai-components.min.js';

    try {
      mkdir(dirname($bundleFilename), 0755, true);
      file_put_contents($bundleFilename, 'console.log("first");');

      expect($state->activate())->toBeTrue();
      expect($filename)->toBeFile();
      $initialState = json_decode(file_get_contents($filename) ?: '{}', true);

      expect($initialState)
        ->toBeArray()
        ->toHaveKeys(['active', 'bundleUrl', 'interval', 'version', 'createdAt', 'updatedAt', 'expiresAt']);
      expect(file_get_contents($filename))
        ->toContain('"active": true')
        ->toContain('/js/assegai-components.min.js');

      $initialVersion = $initialState['version'] ?? null;

      file_put_contents($bundleFilename, 'console.log("second");');
      expect($state->synchronize())->toBeTrue();

      $updatedState = json_decode(file_get_contents($filename) ?: '{}', true);

      expect($updatedState['version'] ?? null)->not->toBe($initialVersion);

      $state->deactivate();
      expect($filename)->toBeFile();

      $inactiveState = json_decode(file_get_contents($filename) ?: '{}', true);

      expect($inactiveState)
        ->toBeArray()
        ->toHaveKeys(['active', 'bundleUrl', 'interval', 'version', 'createdAt', 'updatedAt', 'expiresAt']);
      expect($inactiveState['active'] ?? null)->toBeFalse();
      expect($inactiveState['bundleUrl'] ?? null)->toBe('/js/assegai-components.min.js');
    } finally {
      deleteWebComponentCommandWorkspace($workspace);
    }
  });


  it('treats interrupted watch exits as a normal shutdown', function () {
    $workspace = createWebComponentCommandWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $command = new class extends WatchWebComponents {
        protected function watchComponents(WebComponentBuilder $builder, string $workspace, bool $hotReload): int
        {
          return 130;
        }
      };

      $commandTester = new CommandTester($command);
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($commandTester->getDisplay())->not->toContain('Failed to watch Web Components');
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentCommandWorkspace($workspace);
    }
  });

  it('enables hot reload for wc:watch by default and allows opting out', function () {
    $workspace = createWebComponentCommandWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $command = new class extends WatchWebComponents {
        /** @var array<int, mixed> */
        public array $calls = [];

        protected function watchComponents(WebComponentBuilder $builder, string $workspace, bool $hotReload): int
        {
          $this->calls[] = [
            'workspace' => $workspace,
            'hotReload' => $hotReload,
          ];

          return Command::SUCCESS;
        }
      };

      $commandTester = new CommandTester($command);
      $status = $commandTester->execute([
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toHaveCount(1);
      expect($command->calls[0]['hotReload'])->toBeTrue();
      expect($commandTester->getDisplay())->toContain('Watching Web Components with hot reload');

      $withoutHotReload = new class extends WatchWebComponents {
        /** @var array<int, mixed> */
        public array $calls = [];

        protected function watchComponents(WebComponentBuilder $builder, string $workspace, bool $hotReload): int
        {
          $this->calls[] = [
            'workspace' => $workspace,
            'hotReload' => $hotReload,
          ];

          return Command::SUCCESS;
        }
      };

      $withoutHotReloadTester = new CommandTester($withoutHotReload);
      $status = $withoutHotReloadTester->execute([
        '--directory' => $workspace,
        '--no-hot-reload' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($withoutHotReload->calls)->toHaveCount(1);
      expect($withoutHotReload->calls[0]['hotReload'])->toBeFalse();
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentCommandWorkspace($workspace);
    }
  });
});
