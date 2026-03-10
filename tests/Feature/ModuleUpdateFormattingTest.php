<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

function createModuleFormattingWorkspace(string $moduleContents): string
{
  $workspace = __DIR__ . '/../.tmp/' . uniqid('module-formatting-', true);

  if (! mkdir($workspace . '/src', 0755, true) && ! is_dir($workspace . '/src')) {
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
  file_put_contents($workspace . '/src/FormattingModule.php', $moduleContents);

  return $workspace;
}

function deleteModuleFormattingWorkspace(string $directory): void
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

describe('update_module_file formatting', function () {
  it('preserves single-line imports formatting', function () {
    $moduleContents = <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\App\Existing\ExistingModule;

#[Module(
  providers: [],
  controllers: [],
  imports: [ExistingModule::class]
)]
class FormattingModule
{
}
PHP;

    $workspace = createModuleFormattingWorkspace($moduleContents);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      expect(update_module_file([
        'use' => ['Assegai\App\Profiles\ProfilesModule'],
        'imports' => ['ProfilesModule::class'],
      ], 'FormattingModule'))->toBe(Command::SUCCESS);

      $contents = file_get_contents($workspace . '/src/FormattingModule.php');

      expect($contents)->toContain('imports: [ExistingModule::class, ProfilesModule::class]');
      expect($contents)->not->toContain("imports: [" . PHP_EOL . '  ExistingModule::class');
      expect($contents)->toContain('use Assegai\App\Profiles\ProfilesModule;');
    } finally {
      chdir($previousWorkingDirectory);
      deleteModuleFormattingWorkspace($workspace);
    }
  });

  it('preserves tab-indented multiline imports formatting', function () {
    $moduleContents = <<<PHP
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;
use Assegai\App\Existing\ExistingModule;

#[Module(
\tproviders: [],
\tcontrollers: [],
\timports: [
\t\tExistingModule::class,
\t]
)]
class FormattingModule
{
}
PHP;

    $workspace = createModuleFormattingWorkspace($moduleContents);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      expect(update_module_file([
        'use' => ['Assegai\App\Profiles\ProfilesModule'],
        'imports' => ['ProfilesModule::class'],
      ], 'FormattingModule'))->toBe(Command::SUCCESS);

      $contents = file_get_contents($workspace . '/src/FormattingModule.php');

      expect($contents)->toContain(<<<PHP
\timports: [
\t\tExistingModule::class,
\t\tProfilesModule::class,
\t]
PHP);
      expect($contents)->toContain('use Assegai\App\Profiles\ProfilesModule;');
    } finally {
      chdir($previousWorkingDirectory);
      deleteModuleFormattingWorkspace($workspace);
    }
  });

  it('preserves property indentation when inserting a missing imports entry', function () {
    $moduleContents = <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
        providers: [],
        controllers: []
)]
class FormattingModule
{
}
PHP;

    $workspace = createModuleFormattingWorkspace($moduleContents);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      expect(update_module_file([
        'use' => ['Assegai\App\Profiles\ProfilesModule'],
        'imports' => ['ProfilesModule::class'],
      ], 'FormattingModule'))->toBe(Command::SUCCESS);

      $contents = file_get_contents($workspace . '/src/FormattingModule.php');

      expect($contents)->toContain(<<<'PHP'
        controllers: [],
        imports: [ProfilesModule::class]
PHP);
      expect($contents)->toContain('use Assegai\App\Profiles\ProfilesModule;');
    } finally {
      chdir($previousWorkingDirectory);
      deleteModuleFormattingWorkspace($workspace);
    }
  });

  it('uses the active output instance for colored update logging', function () {
    $moduleContents = <<<'PHP'
<?php

namespace Assegai\App;

use Assegai\Core\Attributes\Modules\Module;

#[Module(
  providers: [],
  controllers: []
)]
class FormattingModule
{
}
PHP;

    $workspace = createModuleFormattingWorkspace($moduleContents);
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

      expect(update_module_file([
        'use' => ['Assegai\App\Profiles\ProfilesModule'],
        'imports' => ['ProfilesModule::class'],
      ], 'FormattingModule', $output))->toBe(Command::SUCCESS);

      $renderedOutput = $output->fetch();

      expect($renderedOutput)->toContain("\e[34mUPDATE")
        ->toContain('FormattingModule.php');
    } finally {
      chdir($previousWorkingDirectory);
      deleteModuleFormattingWorkspace($workspace);
    }
  });
});
