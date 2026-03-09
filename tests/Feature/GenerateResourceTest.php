<?php

use Assegai\Console\Commands\Generate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createGeneratorWorkspace(): string
{
  $workspace = __DIR__ . '/../.tmp/' . uniqid('generate-resource-', true);

  if (! mkdir($workspace, 0755, true) && ! is_dir($workspace)) {
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

  mkdir($workspace . '/src/Users', 0755, true);

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

  file_put_contents($workspace . '/src/Users/UsersModule.php', <<<'PHP'
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
PHP);

  return $workspace;
}

function deleteDirectory(string $directory): void
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

describe('Generate resource', function () {
  it('nests the generated resource and updates the nearest parent module', function () {
    $workspace = createGeneratorWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'resource',
        'name' => 'users/profiles',
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::SUCCESS);

      $generatedModule = $workspace . '/src/Users/Profiles/ProfilesModule.php';
      $parentModule = $workspace . '/src/Users/UsersModule.php';
      $appModule = $workspace . '/src/AppModule.php';

      expect($generatedModule)->toBeFile();
      expect($workspace . '/src/Profiles')->not->toBeDirectory();
      expect(file_get_contents($generatedModule))->toContain('namespace Assegai\App\Users\Profiles;');
      expect(file_get_contents($parentModule))->toContain('use Assegai\App\Users\Profiles\ProfilesModule;');
      expect(file_get_contents($parentModule))->toContain('imports: [ProfilesModule::class]');
      expect(file_get_contents($appModule))->not->toContain('ProfilesModule::class');
    } finally {
      chdir($previousWorkingDirectory);
      deleteDirectory($workspace);
    }
  });
});
