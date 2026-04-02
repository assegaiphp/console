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
        'runtime' => 'php',
        'host' => '127.0.0.1',
        'port' => 5050,
        'openBrowser' => false,
      ],
    ],
    'apiDocs' => [
      'enabled' => true,
      'exportOnServe' => false,
      'exportPath' => 'generated/openapi.json',
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
      expect($command->calls[1]['command'])->toContain("ASSEGAI_RUNTIME='php'");
      expect($command->calls[1]['command'])->toContain('php -S 127.0.0.1:5050');
      expect($command->calls[1]['command'])->toContain($workspace . '/index.php');
      expect($command->calls[2])->toMatchArray([
        'type' => 'stop-watch',
        'root' => $workspace,
      ]);
      expect($commandTester->getDisplay())->toContain('Assegai dev server listening on http://127.0.0.1:5050 using the php runtime');

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


  it('treats interrupted serve exits as a normal shutdown in dev mode', function () {
    $workspace = createServeWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $command = new class extends Serve {
        public array $calls = [];

        protected function startWebComponentWatchProcess(string $root, \Symfony\Component\Console\Output\OutputInterface $output): mixed
        {
          $this->calls[] = ['type' => 'watch', 'root' => $root];
          return (object) ['watching' => true];
        }

        protected function stopWebComponentWatchProcess(mixed $process, string $root): void
        {
          $this->calls[] = ['type' => 'stop-watch', 'root' => $root];
        }

        protected function runServeCommand(string $command): int
        {
          $this->calls[] = ['type' => 'serve', 'command' => $command];
          return 130;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
        '--dev' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toHaveCount(3);
      expect($command->calls[2])->toMatchArray([
        'type' => 'stop-watch',
        'root' => $workspace,
      ]);
      expect($tester->getDisplay())->not->toContain('Failed to serve the project');
    } finally {
      chdir($previousWorkingDirectory);
      deleteServeWorkspace($workspace);
    }
  });

  it('can export OpenAPI on serve when configured', function () {
    $workspace = createServeWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    $config = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);
    $config['apiDocs']['exportOnServe'] = true;
    file_put_contents($workspace . '/assegai.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    chdir($workspace);

    try {
      $command = new class extends Serve {
        public array $calls = [];

        protected function writeOpenApiExport(string $root, string $outputFile, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->calls[] = ['type' => 'export-openapi', 'root' => $root, 'output' => $outputFile];
          return Command::SUCCESS;
        }

        protected function runServeCommand(string $command): int
        {
          $this->calls[] = ['type' => 'serve', 'command' => $command];
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toHaveCount(2);
      expect($command->calls[0])->toMatchArray([
        'type' => 'export-openapi',
        'root' => $workspace,
        'output' => $workspace . '/generated/openapi.json',
      ]);
      expect($command->calls[1]['type'])->toBe('serve');
    } finally {
      chdir($previousWorkingDirectory);
      deleteServeWorkspace($workspace);
    }
  });

  it('can boot the OpenSwoole runtime through the serve command', function () {
    $workspace = createServeWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    $config = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);
    $config['development']['server']['runtime'] = 'openswoole';
    $config['development']['server']['host'] = '127.0.0.1';
    $config['development']['server']['port'] = 9510;
    file_put_contents($workspace . '/assegai.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    chdir($workspace);

    try {
      $command = new class extends Serve {
        public array $calls = [];

        protected function validateRuntimeAvailability(string $runtime): ?string
        {
          return null;
        }

        protected function runServeCommand(string $command): int
        {
          $this->calls[] = ['type' => 'serve', 'command' => $command];
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toHaveCount(1);
      expect($command->calls[0]['command'])->toContain("ASSEGAI_RUNTIME='openswoole'");
      expect($command->calls[0]['command'])->toContain("ASSEGAI_HOST='127.0.0.1'");
      expect($command->calls[0]['command'])->toContain("ASSEGAI_PORT='9510'");
      expect($command->calls[0]['command'])->toContain("ASSEGAI_WORKING_DIR='$workspace'");
      expect($command->calls[0]['command'])->toContain(escapeshellarg(PHP_BINARY));
      expect($command->calls[0]['command'])->toContain($workspace . '/bootstrap.php');
      expect($command->calls[0]['command'])->not->toContain('php -S');
      expect($tester->getDisplay())->toContain('using the openswoole runtime');
    } finally {
      chdir($previousWorkingDirectory);
      deleteServeWorkspace($workspace);
    }
  });

  it('fails early when the selected runtime is unavailable', function () {
    $workspace = createServeWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $command = new class extends Serve {
        protected function validateRuntimeAvailability(string $runtime): ?string
        {
          return $runtime === 'openswoole'
            ? 'The OpenSwoole runtime requires the openswoole PHP extension. Install and enable ext-openswoole before serving with --runtime=openswoole.'
            : null;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
        '--runtime' => 'openswoole',
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($tester->getDisplay())->toContain('requires the openswoole PHP extension');
    } finally {
      chdir($previousWorkingDirectory);
      deleteServeWorkspace($workspace);
    }
  });

  it('fails early when the configured OpenSwoole settings are invalid', function () {
    $workspace = createServeWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    $config = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);
    $config['development']['server']['runtime'] = 'openswoole';
    $config['development']['server']['openswoole'] = [
      'workerNum' => 0,
    ];
    file_put_contents($workspace . '/assegai.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    chdir($workspace);

    try {
      $command = new class extends Serve {
        protected function validateRuntimeAvailability(string $runtime): ?string
        {
          return null;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($tester->getDisplay())->toContain('workerNum');
      expect($tester->getDisplay())->toContain('greater than or equal to 1');
    } finally {
      chdir($previousWorkingDirectory);
      deleteServeWorkspace($workspace);
    }
  });

  it('fails early when the serve binding is invalid', function () {
    $workspace = createServeWorkspace();

    try {
      $command = new class extends Serve {
        protected function validateRuntimeAvailability(string $runtime): ?string
        {
          return null;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
        '--host' => '',
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($tester->getDisplay())->toContain('host must be a non-empty string');
    } finally {
      deleteServeWorkspace($workspace);
    }
  });

  it('passes the configured project root into the runtime environment prefix', function () {
    $workspace = createServeWorkspace();

    try {
      $command = new class extends Serve {
        public array $calls = [];

        protected function validateRuntimeAvailability(string $runtime): ?string
        {
          return null;
        }

        protected function runServeCommand(string $command): int
        {
          $this->calls[] = ['type' => 'serve', 'command' => $command];
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--root' => $workspace,
        '--runtime' => 'openswoole',
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($command->calls)->toHaveCount(1);
      expect($command->calls[0]['command'])->toContain("ASSEGAI_WORKING_DIR='$workspace'");
    } finally {
      deleteServeWorkspace($workspace);
    }
  });
});
