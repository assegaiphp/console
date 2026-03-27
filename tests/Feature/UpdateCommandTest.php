<?php

use Assegai\Console\Commands\Update;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createUpdateWorkspace(array $options = []): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('update-workspace-', true);

  if (! mkdir($workspace . '/src', 0755, true) && ! is_dir($workspace . '/src')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/bootstrap.php', "<?php\n");
  file_put_contents($workspace . '/composer.json', json_encode($options['composer'] ?? [
    'name' => 'acme/legacy-app',
    'autoload' => [
      'psr-4' => [
        'Acme\\Legacy\\' => 'src/',
      ],
    ],
    'require' => [
      'php' => '>=8.3',
      PACKAGE_NAME_CORE => '^0.6.0',
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/assegai.json', json_encode($options['assegai'] ?? [
    'name' => 'Legacy App',
    'projectType' => 'project',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  if (($options['package_json'] ?? false) === true) {
    file_put_contents($workspace . '/package.json', json_encode([
      'name' => 'legacy-app',
      'private' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  foreach ($options['files'] ?? [] as $relativeFilename => $contents) {
    $filename = $workspace . '/' . $relativeFilename;
    $directory = dirname($filename);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
      throw new RuntimeException("Failed to create directory: $directory");
    }

    file_put_contents($filename, $contents);
  }

  return $workspace;
}

function deleteUpdateWorkspace(string $directory): void
{
  if (! is_dir($directory)) {
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

describe('Update command', function () {
  it('migrates legacy workspaces and upgrades the tied core and orm packages', function () {
    $workspace = createUpdateWorkspace([
      'package_json' => true,
      'files' => [
        'src/Users/UsersService.php' => <<<'PHP'
<?php

namespace Acme\Legacy\Users;

use Assegai\Orm\Attributes\InjectRepository;
PHP,
      ],
    ]);

    try {
      $command = new class extends Update {
        public array $composerCalls = [];
        public array $frontendCalls = [];

        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerCalls[] = [
            'workspace' => $workspace,
            'packages' => $packages,
          ];

          return Command::SUCCESS;
        }

        protected function runFrontendInstall(string $workspace, string $packageManager, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->frontendCalls[] = [
            'workspace' => $workspace,
            'packageManager' => $packageManager,
          ];

          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
      ]);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);
      $assegaiConfig = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);

      expect($status)->toBe(Command::SUCCESS)
          ->and($composer['require'][PACKAGE_NAME_CORE])->toBe(RECOMMENDED_CORE_VERSION_CONSTRAINT)
          ->and($composer['require'][PACKAGE_NAME_ORM])->toBe(RECOMMENDED_ORM_VERSION_CONSTRAINT)
          ->and($assegaiConfig['webComponents']['hotReload']['enabled'])->toBeTrue()
          ->and($command->composerCalls[0]['packages'])->toBe([PACKAGE_NAME_CORE, PACKAGE_NAME_ORM])
          ->and($command->frontendCalls[0]['packageManager'])->toBe('npm');
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });

  it('keeps non-orm workspaces on the core-only upgrade path', function () {
    $workspace = createUpdateWorkspace();

    try {
      $command = new class extends Update {
        public array $composerCalls = [];
        public array $frontendCalls = [];

        protected function runComposerUpgrade(string $workspace, array $packages, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->composerCalls[] = $packages;
          return Command::SUCCESS;
        }

        protected function runFrontendInstall(string $workspace, string $packageManager, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
          $this->frontendCalls[] = $packageManager;
          return Command::SUCCESS;
        }
      };

      $tester = new CommandTester($command);
      $status = $tester->execute([
        '--directory' => $workspace,
      ]);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);

      expect($status)->toBe(Command::SUCCESS)
          ->and($command->composerCalls[0])->toBe([PACKAGE_NAME_CORE])
          ->and($command->frontendCalls)->toBe([])
          ->and($composer['require'])->not->toHaveKey(PACKAGE_NAME_ORM);
    } finally {
      deleteUpdateWorkspace($workspace);
    }
  });
});
