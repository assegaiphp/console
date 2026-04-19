<?php

use Assegai\Console\Commands\Database\DatabaseConfigure;
use Assegai\Console\Prompts\CliPrompt;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

if (! function_exists('env')) {
  function env(string $key, mixed $default = null): mixed
  {
    return $default;
  }
}

function createDatabaseConfigureWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('database-configure-', true);

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

  mkdir($workspace . '/src', 0755, true);
  file_put_contents($workspace . '/src/AppModule.php', <<<'PHP2'
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
PHP2);

  return $workspace;
}

function deleteDatabaseConfigureWorkspace(string $directory): void
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

dataset('database-configure-types', [
  'mysql' => ['--mysql', 'mysql', '3306', 'root'],
  'mariadb' => ['--mariadb', 'mariadb', '3306', 'root'],
  'mssql' => ['--mssql', 'mssql', '1433', 'sa'],
]);

describe('database:configure', function () {
  it('offers module data_source enablement after saving the database config', function (string $flag, string $type, string $port, string $user) {
    $workspace = createDatabaseConfigureWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($workspace);

    try {
      CliPrompt::fake([
        'select' => ['all'],
      ]);

      $command = new DatabaseConfigure();
      $command->setHelperSet(new HelperSet([
        'question' => new QuestionHelper(),
      ]));

      $commandTester = new CommandTester($command);
      $commandTester->setInputs(['0']);

      $status = $commandTester->execute([
        'database_name' => 'blog',
        $flag => true,
        '--host' => '127.0.0.1',
        '--port' => $port,
        '--user' => $user,
        '--password' => 'secret',
      ], [
        'interactive' => true,
      ]);

      $config = require $workspace . '/config/default.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($config['databases'][$type]['blog']['password'])->toBe('secret');
      expect(file_get_contents($workspace . '/src/AppModule.php'))
        ->toContain("'data_source' => '$type:blog'");
    } finally {
      CliPrompt::flushFake();
      chdir($previousWorkingDirectory);
      deleteDatabaseConfigureWorkspace($workspace);
    }
  })->with('database-configure-types');
});
