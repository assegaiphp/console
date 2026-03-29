<?php

use Assegai\Console\Commands\Add;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createAddCommandWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('add-command-', true);

  if (!mkdir($workspace, 0755, true) && !is_dir($workspace)) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  mkdir($workspace . '/src', 0755, true);

  file_put_contents($workspace . '/composer.json', json_encode([
    'name' => 'assegai/test-app',
    'autoload' => [
      'psr-4' => [
        'Assegai\\App\\' => 'src/',
      ],
    ],
    'require' => [
      'php' => '>=8.3',
      'assegaiphp/core' => '^0.7.0',
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/assegai.json', json_encode([
    'name' => 'test-app',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/bootstrap.php', <<<'PHP'
<?php

use Assegai\App\AppModule;
use Assegai\Core\AssegaiFactory;

function bootstrap(): void
{
  AssegaiFactory::create(AppModule::class)->run();
}
PHP);

  file_put_contents($workspace . '/src/AppModule.php', <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: []
)]
class AppModule
{
}
PHP);

  return $workspace;
}

function removeAddCommandWorkspace(string $directory): void
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

describe('Add command', function () {
  it('adds events to composer config, assegai config, and the root module without installing', function () {
    $workspace = createAddCommandWorkspace();

    try {
      $tester = new CommandTester(new Add());
      $status = $tester->execute([
        'package' => 'events',
        '--directory' => $workspace,
        '--no-install' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);

      $composer = json_decode(file_get_contents($workspace . '/composer.json') ?: '', true);
      $assegai = json_decode(file_get_contents($workspace . '/assegai.json') ?: '', true);
      $appModule = file_get_contents($workspace . '/src/AppModule.php') ?: '';

      expect($composer['require']['assegaiphp/events'] ?? null)->toBe('*');
      expect($assegai['events']['wildcards'] ?? null)->toBeTrue();
      expect($assegai['events']['delimiter'] ?? null)->toBe('.');
      expect(array_key_exists('maxListeners', $assegai['events'] ?? []))->toBeTrue();
      expect($appModule)->toContain('use Assegai\\Events\\Assegai\\EventsModule;');
      expect($appModule)->toContain('EventsModule::class');
    } finally {
      removeAddCommandWorkspace($workspace);
    }
  });

  it('fails cleanly for unsupported packages', function () {
    $workspace = createAddCommandWorkspace();

    try {
      $tester = new CommandTester(new Add());
      $status = $tester->execute([
        'package' => 'mystery',
        '--directory' => $workspace,
        '--no-install' => true,
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($tester->getDisplay())->toContain('Unsupported package');
    } finally {
      removeAddCommandWorkspace($workspace);
    }
  });
});
