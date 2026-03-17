<?php

use Assegai\Console\Commands\Serve;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createServeWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('serve-command-', true);

  if (!mkdir($workspace . '/public', 0755, true) && !is_dir($workspace . '/public')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/bootstrap.php', "<?php\n");
  file_put_contents($workspace . '/index.php', "<?php\nreturn false;\n");
  file_put_contents($workspace . '/assegai.json', json_encode([
    'development' => [
      'server' => [
        'host' => '127.0.0.1',
        'port' => 5050,
        'openBrowser' => false,
      ],
    ],
    'webComponents' => [
      'enabled' => true,
      'output' => 'public/js/assegai-components.min.js',
      'hotReload' => [
        'enabled' => true,
        'path' => 'public/.assegai/wc-hot-reload.json',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  return $workspace;
}

function deleteServeWorkspace(string $directory): void
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

describe('Serve', function () {
  it('starts the Web Components watcher in dev mode and keeps normal serve untouched', function () {
    $workspace = createServeWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $command = new class extends Serve {
        public array $calls = [];

        protected function startWebComponentWatchProcess(string $root, \Symfony\Component\Console\Output\OutputInterface $output): mixed
        {
          $this->calls[] = ['type' => 'watch', 'root' => $root];
          return (object)['watching' => true];
        }

        protected function stopWebComponentWatchProcess(mixed $process, string $root): void
        {
          $this->calls[] = ['type' => 'stop-watch', 'root' => $root];
        }

        protected function runServeCommand(string $command): int
        {
          $this->calls[] = ['type' => 'serve', 'command' => $command];
          return Command::SUCCESS;
        }
      };

      $commandTester = new CommandTester($command);
      $status = $commandTester->execute([
        '--root' => $workspace,
        '--dev' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toHaveCount(3);
      expect($command->calls[0])->toMatchArray([
        'type' => 'watch',
        'root' => $workspace,
      ]);
      expect($command->calls[1]['type'])->toBe('serve');
      expect($command->calls[1]['command'])->toContain('php -S 127.0.0.1:5050');
      expect($command->calls[1]['command'])->toContain($workspace . '/index.php');
      expect($command->calls[2])->toMatchArray([
        'type' => 'stop-watch',
        'root' => $workspace,
      ]);
      expect($commandTester->getDisplay())->toContain('Assegai dev server listening on http://127.0.0.1:5050');

      $regularServe = new class extends Serve {
        public array $calls = [];

        protected function startWebComponentWatchProcess(string $root, \Symfony\Component\Console\Output\OutputInterface $output): mixed
        {
          $this->calls[] = ['type' => 'watch', 'root' => $root];
          return null;
        }

        protected function runServeCommand(string $command): int
        {
          $this->calls[] = ['type' => 'serve', 'command' => $command];
          return Command::SUCCESS;
        }
      };

      $regularTester = new CommandTester($regularServe);
      $status = $regularTester->execute([
        '--root' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($regularServe->calls)->toHaveCount(1);
      expect($regularServe->calls[0]['type'])->toBe('serve');
    } finally {
      chdir($previousWorkingDirectory);
      deleteServeWorkspace($workspace);
    }
  });
});
