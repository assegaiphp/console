<?php

use Assegai\Console\ApplicationFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

function createPackageDiscoveryWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('package-discovery-', true);

  if (!mkdir($workspace . '/vendor/assegaiphp/orm/src/Assegai/Orm/Assegai/Console/Commands/Database', 0755, true) && !is_dir($workspace . '/vendor/assegaiphp/orm/src/Assegai/Orm/Assegai/Console/Commands/Database')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents($workspace . '/vendor/assegaiphp/orm/composer.json', json_encode([
    'name' => 'assegaiphp/orm',
    'extra' => [
      'assegai' => [
        'aliases' => ['orm'],
        'commands' => [
          'Assegai\\Orm\\Assegai\\Console\\Commands\\Database\\DatabaseSetup',
        ],
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/vendor/assegaiphp/orm/src/Assegai/Orm/Assegai/Console/Commands/Database/DatabaseSetup.php', <<<'PHP'
<?php

namespace Assegai\Orm\Assegai\Console\Commands\Database;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
  name: 'database:setup',
  description: 'Setup the database',
  aliases: ['db:setup']
)]
class DatabaseSetup extends Command
{
}
PHP);

  file_put_contents($workspace . '/vendor/autoload.php', <<<'PHP'
<?php

spl_autoload_register(function (string $class): void {
  $prefix = 'Assegai\\Orm\\Assegai\\Console\\Commands\\Database\\';

  if (!str_starts_with($class, $prefix)) {
    return;
  }

  $relative = substr($class, strlen($prefix));
  $filename = __DIR__ . '/assegaiphp/orm/src/Assegai/Orm/Assegai/Console/Commands/Database/' . str_replace('\\', '/', $relative) . '.php';

  if (is_file($filename)) {
    require_once $filename;
  }
});
PHP);

  return $workspace;
}

function removePackageDiscoveryWorkspace(string $directory): void
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

describe('ApplicationFactory package command discovery', function () {
  it('loads command classes declared by installed package manifests', function () {
    $workspace = createPackageDiscoveryWorkspace();

    try {
      $application = ApplicationFactory::create($workspace);

      expect($application->has('database:setup'))->toBeTrue();
      expect($application->find('database:setup'))->toBeInstanceOf(Command::class);
    } finally {
      removePackageDiscoveryWorkspace($workspace);
    }
  });
});
