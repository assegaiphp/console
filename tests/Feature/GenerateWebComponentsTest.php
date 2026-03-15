<?php

use Assegai\Console\Commands\Generate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createWebComponentGeneratorWorkspace(array $webComponentConfig = []): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('generate-web-components-', true);

  if (!mkdir($workspace . '/src', 0755, true) && !is_dir($workspace . '/src')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        'Assegai\\App\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/assegai.json', json_encode([
    'webComponents' => $webComponentConfig,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/bootstrap.php', "<?php\n");

  file_put_contents($workspace . '/src/AppModule.php', <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  declarations: [],
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

function deleteWebComponentGeneratorWorkspace(string $directory): void
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

describe('Generate Web Components', function () {
  it('generates a standalone Web Component and shared runtime files', function () {
    $workspace = createWebComponentGeneratorWorkspace(['prefix' => 'acme']);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'wc',
        'name' => 'ui/alert',
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($workspace . '/src/WebComponents/Ui/Alert/AlertComponent.wc.ts')->toBeFile();
      expect($workspace . '/src/WebComponents/runtime/index.ts')->toBeFile();
      expect(str_contains(
        file_get_contents($workspace . '/src/WebComponents/Ui/Alert/AlertComponent.wc.ts') ?: '',
        "defineElement('acme-alert', AlertElement);"
      ))->toBeTrue();
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentGeneratorWorkspace($workspace);
    }
  });

  it('generates paired Web Components for components and pages when requested', function () {
    $workspace = createWebComponentGeneratorWorkspace(['prefix' => 'acme']);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      expect($commandTester->execute([
        'schematic' => 'component',
        'name' => 'user-card',
        '--directory' => $workspace,
        '--wc' => true,
      ]))->toBe(Command::SUCCESS);

      expect(str_contains(
        file_get_contents($workspace . '/src/UserCard/UserCardComponent.php') ?: '',
        "selector: 'acme-user-card'"
      ))->toBeTrue();
      expect(str_contains(
        file_get_contents($workspace . '/src/UserCard/UserCardComponent.wc.ts') ?: '',
        "defineElement('acme-user-card', UserCardElement);"
      ))->toBeTrue();

      expect($commandTester->execute([
        'schematic' => 'page',
        'name' => 'about',
        '--directory' => $workspace,
        '--wc' => true,
      ]))->toBe(Command::SUCCESS);

      expect(str_contains(
        file_get_contents($workspace . '/src/About/AboutComponent.php') ?: '',
        "selector: 'acme-about'"
      ))->toBeTrue();
      expect(str_contains(
        file_get_contents($workspace . '/src/About/AboutComponent.wc.ts') ?: '',
        "defineElement('acme-about', AboutElement);"
      ))->toBeTrue();
      expect(str_contains(
        file_get_contents($workspace . '/src/AppModule.php') ?: '',
        'AboutModule::class'
      ))->toBeTrue();
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentGeneratorWorkspace($workspace);
    }
  });
});
