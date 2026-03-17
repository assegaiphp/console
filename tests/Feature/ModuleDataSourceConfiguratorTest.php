<?php

use Assegai\Console\Core\Modules\ModuleDataSourceConfigurator;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Tests\Mocks\MockInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Output\BufferedOutput;

if (! function_exists('env')) {
  function env(string $key, mixed $default = null): mixed
  {
    return $default;
  }
}

function createModuleDataSourceWorkspace(array $modules = []): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('module-data-source-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        'Assegai\\App\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/assegai.json', "{}\n");
  file_put_contents($workspace . '/bootstrap.php', "<?php\n");
  copy(__DIR__ . '/../../templates/config/default.php', $workspace . '/config/default.php');

  $defaultModules = [
    'src/AppModule.php' => <<<'PHP'
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
PHP,
    'src/Users/UsersModule.php' => <<<'PHP'
<?php

namespace Assegai\App\Users;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: []
)]
class UsersModule
{
}
PHP,
  ];

  foreach ([...$defaultModules, ...$modules] as $relativeFilename => $contents) {
    $filename = $workspace . '/' . $relativeFilename;
    $directory = dirname($filename);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
      throw new RuntimeException("Failed to create directory: $directory");
    }

    file_put_contents($filename, $contents);
  }

  return $workspace;
}

function deleteModuleDataSourceWorkspace(string $directory): void
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

describe('Module data_source configurator', function () {
  it('can apply a data_source to specific modules without touching others', function () {
    $workspace = createModuleDataSourceWorkspace();
    $output = new BufferedOutput();
    $configurator = new ModuleDataSourceConfigurator(
      new MockInput(),
      $output,
      new QuestionHelper(),
      $workspace
    );

    try {
      $status = $configurator->configureForModules('blog', ['AppModule.php']);

      expect($status)->toBe(Command::SUCCESS);
      expect(file_get_contents($workspace . '/src/AppModule.php'))
        ->toContain("'data_source' => 'blog'");
      expect(file_get_contents($workspace . '/src/Users/UsersModule.php'))
        ->not->toContain('data_source');
    } finally {
      deleteModuleDataSourceWorkspace($workspace);
    }
  });

  it('warns and skips colliding data_source values by default', function () {
    $workspace = createModuleDataSourceWorkspace([
      'src/AppModule.php' => <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: [],
  config: ['data_source' => 'legacy']
)]
class AppModule
{
}
PHP,
    ]);
    $output = new BufferedOutput();
    $configurator = new ModuleDataSourceConfigurator(
      new MockInput(),
      $output,
      new QuestionHelper(),
      $workspace
    );

    try {
      $status = $configurator->configureForAllModules('blog');

      expect($status)->toBe(Command::SUCCESS);
      expect(file_get_contents($workspace . '/src/AppModule.php'))
        ->toContain("'data_source' => 'legacy'")
        ->not->toContain("'data_source' => 'blog'");
      expect(file_get_contents($workspace . '/src/Users/UsersModule.php'))
        ->toContain("'data_source' => 'blog'");
      expect($output->fetch())
        ->toContain('would collide with')
        ->toContain('src/AppModule.php')
        ->toContain('Left 1 module(s) unchanged');
    } finally {
      deleteModuleDataSourceWorkspace($workspace);
    }
  });

  it('can overwrite colliding data_source values when requested', function () {
    $workspace = createModuleDataSourceWorkspace([
      'src/AppModule.php' => <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: [],
  imports: [],
  config: ['data_source' => 'legacy']
)]
class AppModule
{
}
PHP,
    ]);
    $output = new BufferedOutput();
    $configurator = new ModuleDataSourceConfigurator(
      new MockInput(),
      $output,
      new QuestionHelper(),
      $workspace
    );

    try {
      $status = $configurator->configureForModules('blog', ['AppModule.php'], true);

      expect($status)->toBe(Command::SUCCESS);
      expect(file_get_contents($workspace . '/src/AppModule.php'))
        ->toContain("'data_source' => 'blog'")
        ->not->toContain("'data_source' => 'legacy'");
    } finally {
      deleteModuleDataSourceWorkspace($workspace);
    }
  });

  it('uses prompt-driven module selection when running interactively', function () {
    $workspace = createModuleDataSourceWorkspace();
    $output = new BufferedOutput();
    $configurator = new ModuleDataSourceConfigurator(
      new MockInput([], [], true),
      $output,
      new QuestionHelper(),
      $workspace
    );

    try {
      CliPrompt::fake([
        'select' => ['all'],
      ]);

      $status = $configurator->promptAndConfigure('blog');

      expect($status)->toBe(Command::SUCCESS);
      expect(file_get_contents($workspace . '/src/AppModule.php'))
        ->toContain("'data_source' => 'blog'");
      expect(file_get_contents($workspace . '/src/Users/UsersModule.php'))
        ->toContain("'data_source' => 'blog'");
    } finally {
      CliPrompt::flushFake();
      deleteModuleDataSourceWorkspace($workspace);
    }
  });
});
