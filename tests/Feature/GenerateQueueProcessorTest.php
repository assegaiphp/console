<?php

use Assegai\Console\Commands\Generate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createQueueProcessorWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('generate-queue-processor-', true);

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

  mkdir($workspace . '/src', 0755, true);

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

function deleteQueueProcessorWorkspace(string $directory): void
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

describe('Generate queue processor', function () {
  it('generates a queue processor provider with the requested queue path', function () {
    $workspace = createQueueProcessorWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'qp',
        'name' => 'notifications',
        '--directory' => $workspace,
        '--queue' => 'rabbitmq.notifications',
      ]);

      $generatedFile = $workspace . '/src/Notifications/NotificationsProcessor.php';
      $appModule = $workspace . '/src/AppModule.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($generatedFile)->toBeFile();
      expect(file_get_contents($generatedFile))
        ->toContain('namespace Assegai\App\Notifications;')
        ->toContain('use Assegai\Core\Attributes\Injectable;')
        ->toContain('use Assegai\Core\Queues\Attributes\QueueProcessor;')
        ->toContain('#[Injectable]')
        ->toContain("#[QueueProcessor('rabbitmq.notifications')]")
        ->toContain('class NotificationsProcessor')
        ->toContain('public function process(object $job): void');
      expect(file_get_contents($appModule))
        ->toContain('use Assegai\App\Notifications\NotificationsProcessor;')
        ->toContain('providers: [NotificationsProcessor::class]');
    } finally {
      chdir($previousWorkingDirectory);
      deleteQueueProcessorWorkspace($workspace);
    }
  });

  it('supports a typed job signature when a job class is provided', function () {
    $workspace = createQueueProcessorWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'queue-processor',
        'name' => 'notifications',
        '--directory' => $workspace,
        '--queue' => 'rabbitmq.notifications',
        '--job' => 'Jobs/NotificationJob',
      ]);

      $generatedFile = $workspace . '/src/Notifications/NotificationsProcessor.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($generatedFile)->toBeFile();
      expect(file_get_contents($generatedFile))
        ->toContain('use Assegai\App\Jobs\NotificationJob;')
        ->toContain('public function process(NotificationJob $job): void');
    } finally {
      chdir($previousWorkingDirectory);
      deleteQueueProcessorWorkspace($workspace);
    }
  });

  it('resolves a bare job name against the local feature Jobs namespace when that folder exists', function () {
    $workspace = createQueueProcessorWorkspace();
    $previousWorkingDirectory = getcwd();

    if (false === $previousWorkingDirectory) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    mkdir($workspace . '/src/Notifications/Jobs', 0755, true);
    file_put_contents($workspace . '/src/Notifications/Jobs/NotificationJob.php', <<<'PHP'
<?php

namespace Assegai\App\Notifications\Jobs;

final class NotificationJob
{
}
PHP);

    chdir($workspace);

    try {
      $commandTester = new CommandTester(new Generate());

      $status = $commandTester->execute([
        'schematic' => 'qp',
        'name' => 'notifications',
        '--directory' => $workspace,
        '--queue' => 'rabbitmq.notifications',
        '--job' => 'notification-job',
      ]);

      $generatedFile = $workspace . '/src/Notifications/NotificationsProcessor.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($generatedFile)->toBeFile();
      expect(file_get_contents($generatedFile))
        ->toContain('use Assegai\App\Notifications\Jobs\NotificationJob;')
        ->toContain('public function process(NotificationJob $job): void');
    } finally {
      chdir($previousWorkingDirectory);
      deleteQueueProcessorWorkspace($workspace);
    }
  });
});
