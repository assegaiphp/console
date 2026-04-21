<?php

use Assegai\Console\Commands\Generate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @param array<string, mixed> $webComponentConfig
 */
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

  file_put_contents($workspace . '/src/AppModule.php', <<<'INNER'
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
INNER);

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
      expect($workspace . '/src/WebComponents/runtime/AssegaiElement.ts')->toBeFile();
      expect(str_contains(
        file_get_contents($workspace . '/src/WebComponents/Ui/Alert/AlertComponent.wc.ts') ?: '',
        "defineElement('acme-alert', AlertElement);"
      ))->toBeTrue();
      expect(str_contains(
        file_get_contents($workspace . '/src/WebComponents/Ui/Alert/AlertComponent.wc.ts') ?: '',
        "const name: string = this.getAttribute('name') || 'alert';"
      ))->toBeTrue();
      expect(str_contains(
        file_get_contents($workspace . '/src/WebComponents/Ui/Alert/AlertComponent.wc.ts') ?: '',
        "this.shadow.innerHTML = `"
      ))->toBeTrue();
      expect(str_contains(
        file_get_contents($workspace . '/src/WebComponents/runtime/AssegaiElement.ts') ?: '',
        'protected get shadow(): ShadowRoot'
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
      expect(str_contains(
        file_get_contents($workspace . '/src/UserCard/UserCardComponent.wc.ts') ?: '',
        "const name: string = this.getAttribute('name') || 'user-card';"
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
        file_get_contents($workspace . '/src/About/AboutComponent.wc.ts') ?: '',
        "const name: string = this.getAttribute('name') || 'about';"
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

  it('declares nested generated components in the nearest existing module', function () {
    $workspace = createWebComponentGeneratorWorkspace(['prefix' => 'acme']);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    mkdir($workspace . '/src/Heroes', 0755, true);
    file_put_contents($workspace . '/src/Heroes/HeroesModule.php', <<<'INNER'
<?php

namespace Assegai\App\Heroes;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  declarations: [],
  providers: [],
  controllers: [],
  imports: []
)]
class HeroesModule
{
}
INNER);

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      expect($commandTester->execute([
        'schematic' => 'component',
        'name' => 'heroes/hero-detail',
        '--directory' => $workspace,
      ]))->toBe(Command::SUCCESS);

      expect($workspace . '/src/Heroes/HeroDetail/HeroDetailComponent.php')->toBeFile();
      expect(file_get_contents($workspace . '/src/Heroes/HeroesModule.php') ?: '')
        ->toContain('use Assegai\\App\\Heroes\\HeroDetail\\HeroDetailComponent;')
        ->toContain('declarations: [HeroDetailComponent::class]');
      expect(file_get_contents($workspace . '/src/AppModule.php') ?: '')
        ->not->toContain('HeroDetailComponent::class');
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentGeneratorWorkspace($workspace);
    }
  });

  it('can generate a flat component beside the app module', function () {
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
        'name' => 'app',
        '--directory' => $workspace,
        '--flat' => true,
      ]))->toBe(Command::SUCCESS);

      expect($workspace . '/src/AppComponent.php')->toBeFile();
      expect($workspace . '/src/AppComponent.twig')->toBeFile();
      expect($workspace . '/src/AppComponent.css')->toBeFile();
      expect($workspace . '/src/App/AppComponent.php')->not->toBeFile();
      expect(file_get_contents($workspace . '/src/AppComponent.php') ?: '')
        ->toContain('namespace Assegai\\App;')
        ->toContain("selector: 'acme-app'");
      expect(file_get_contents($workspace . '/src/AppModule.php') ?: '')
        ->toContain('use Assegai\\App\\AppComponent;')
        ->toContain('declarations: [AppComponent::class]');
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentGeneratorWorkspace($workspace);
    }
  });

  it('can generate a flat page in an explicit source-relative path', function () {
    $workspace = createWebComponentGeneratorWorkspace(['prefix' => 'acme']);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      expect($commandTester->execute([
        'schematic' => 'page',
        'name' => 'about',
        '--directory' => $workspace,
        '--path' => 'marketing/landing',
        '--flat' => true,
        '--wc' => true,
      ]))->toBe(Command::SUCCESS);

      expect($workspace . '/src/Marketing/Landing/AboutComponent.php')->toBeFile();
      expect($workspace . '/src/Marketing/Landing/AboutController.php')->toBeFile();
      expect($workspace . '/src/Marketing/Landing/AboutModule.php')->toBeFile();
      expect($workspace . '/src/Marketing/Landing/AboutService.php')->toBeFile();
      expect($workspace . '/src/Marketing/Landing/AboutComponent.wc.ts')->toBeFile();
      expect($workspace . '/src/Marketing/Landing/About/AboutComponent.php')->not->toBeFile();
      expect(file_get_contents($workspace . '/src/Marketing/Landing/AboutComponent.php') ?: '')
        ->toContain('namespace Assegai\\App\\Marketing\\Landing;')
        ->toContain("selector: 'acme-about'");
      expect(file_get_contents($workspace . '/src/Marketing/Landing/AboutComponent.wc.ts') ?: '')
        ->toContain("defineElement('acme-about', AboutElement);");
      expect(file_get_contents($workspace . '/src/AppModule.php') ?: '')
        ->toContain('use Assegai\\App\\Marketing\\Landing\\AboutModule;')
        ->toContain('imports: [AboutModule::class]');
    } finally {
      chdir($previousWorkingDirectory);
      deleteWebComponentGeneratorWorkspace($workspace);
    }
  });
});
