<?php

use Assegai\Console\Commands\Generate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createGeneratorWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('generate-resource-', true);

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
  it('fails cleanly outside an Assegai workspace before writing files', function () {
    $workspace = sys_get_temp_dir() . '/' . uniqid('invalid-generate-resource-', true);

    if (! mkdir($workspace, 0755, true) && ! is_dir($workspace)) {
      throw new RuntimeException("Failed to create test workspace: $workspace");
    }

    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'r',
        'name' => 'blog-api',
        '--directory' => $workspace,
      ]);

      expect($status)->toBe(Command::FAILURE);
      expect($commandTester->getDisplay())->toContain('This is not a valid Assegai workspace.');
      expect($commandTester->getDisplay())->not->toContain('composer.json not found');
      expect($commandTester->getDisplay())->not->toContain('Warning');
      expect($workspace . '/src/BlogApi')->not->toBeDirectory();
    } finally {
      chdir($previousWorkingDirectory);
      deleteDirectory($workspace);
    }
  });

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

  it('infers REST-friendly controller, dto, entity, and route names from a singular resource name', function () {
    $workspace = createGeneratorWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'r',
        'name' => 'user-profile',
        '--directory' => $workspace,
      ]);

      $resourcePath = $workspace . '/src/UserProfile';
      $controller = $resourcePath . '/UserProfileController.php';
      $service = $resourcePath . '/UserProfileService.php';
      $entity = $resourcePath . '/Entities/UserProfileEntity.php';
      $createDto = $resourcePath . '/DTOs/CreateUserProfileDTO.php';
      $updateDto = $resourcePath . '/DTOs/UpdateUserProfileDTO.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($controller)->toBeFile();
      expect($service)->toBeFile();
      expect($entity)->toBeFile();
      expect($createDto)->toBeFile();
      expect($updateDto)->toBeFile();
      expect(file_get_contents($controller))
        ->toContain('use Assegai\App\UserProfile\DTOs\CreateUserProfileDTO;')
        ->toContain('use Assegai\App\UserProfile\DTOs\UpdateUserProfileDTO;')
        ->toContain("#[Controller('user-profiles')]")
        ->toContain('public function create(#[Body] CreateUserProfileDTO $createUserProfileDTO): string')
        ->toContain('#[Body] UpdateUserProfileDTO $updateUserProfileDTO');
      expect(file_get_contents($service))
        ->toContain('use Assegai\App\UserProfile\DTOs\CreateUserProfileDTO;')
        ->toContain('use Assegai\App\UserProfile\DTOs\UpdateUserProfileDTO;')
        ->toContain('return \'This action returns all user-profiles!\';');
      expect(file_get_contents($entity))
        ->toContain("#[Entity(table: 'user-profiles')]");
    } finally {
      chdir($previousWorkingDirectory);
      deleteDirectory($workspace);
    }
  });
});
