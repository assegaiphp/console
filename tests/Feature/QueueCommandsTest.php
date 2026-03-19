<?php

use Assegai\Console\Commands\Queue\QueueList;
use Assegai\Console\Commands\Queue\QueueWork;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function createQueueWorkspace(): array
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('queue-command-', true);
  $suffix = preg_replace('/[^A-Za-z0-9]/', '', uniqid('', true));
  $namespace = "Assegaiphp\\QueueTest{$suffix}";
  $repoRoot = resolveAssegaiRepoRoot();
  $vendorAutoload = $repoRoot . '/blog-api/vendor/autoload.php';
  $coreFunctions = $repoRoot . '/core/src/Util/Functions.php';

  foreach ([
    $workspace . '/vendor',
    $workspace . '/config',
    $workspace . '/src/Jobs',
    $workspace . '/src/Processors',
    $workspace . '/src/Queue',
    $workspace . '/storage',
  ] as $directory) {
    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
      throw new RuntimeException("Failed to create test directory: {$directory}");
    }
  }

  $autoload = <<<'PHP'
<?php

$prefixes = %s;

spl_autoload_register(static function (string $class) use ($prefixes): void {
  foreach ($prefixes as $prefix => $baseDir) {
    if (!str_starts_with($class, $prefix)) {
      continue;
    }

    $relative = substr($class, strlen($prefix));
    $file = rtrim($baseDir, '/\\') . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
      require_once $file;
    }

    return;
  }
}, prepend: true);

require %s;
require_once %s;

return true;
PHP;

  $prefixes = [
    $namespace . '\\' => $workspace . '/src/',
    'Assegai\\Core\\' => $repoRoot . '/core/src/',
    'Assegai\\Forms\\' => $repoRoot . '/forms/src/',
    'Assegai\\Util\\' => $repoRoot . '/util/src/',
    'Assegai\\Validation\\' => $repoRoot . '/validation/src/',
    'Assegai\\Collections\\' => $repoRoot . '/collections/src/',
    'Assegai\\Orm\\' => $repoRoot . '/orm/src/',
    'Assegai\\Auth\\' => $repoRoot . '/auth/src/',
  ];

  file_put_contents(
    $workspace . '/vendor/autoload.php',
    sprintf(
      $autoload,
      var_export($prefixes, true),
      var_export($vendorAutoload, true),
      var_export($coreFunctions, true),
    )
  );

  file_put_contents($workspace . '/.env', "ENV=dev\nDEBUG_MODE=true\n");
  file_put_contents($workspace . '/composer.json', json_encode([
    'name' => 'assegaiphp/queue-test',
    'autoload' => [
      'psr-4' => [
        $namespace . '\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  file_put_contents($workspace . '/assegai.json', json_encode([
    'name' => 'queue-test',
    'development' => [
      'server' => [
        'host' => '127.0.0.1',
        'port' => 5050,
        'openBrowser' => false,
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  file_put_contents($workspace . '/bootstrap.php', <<<PHP
<?php

use Assegai\Core\AssegaiFactory;
use {$namespace}\AppModule;

require __DIR__ . '/vendor/autoload.php';

\$app = AssegaiFactory::create(AppModule::class);
PHP);

  file_put_contents($workspace . '/config/default.php', "<?php\n\nreturn [];\n");
  file_put_contents($workspace . '/config/queues.php', <<<PHP
<?php

use {$namespace}\Jobs\NotificationJob;
use {$namespace}\Queue\FakeQueue;

return [
  'drivers' => [
    'sync' => FakeQueue::class,
  ],
  'connections' => [
    'sync' => [
      'notifications' => [
        'jobs' => [
          new NotificationJob('hello from queue'),
        ],
      ],
    ],
  ],
];
PHP);

  file_put_contents($workspace . '/src/AppModule.php', <<<PHP
<?php

namespace {$namespace};

use Assegai\Core\Attributes\Modules\Module;
use {$namespace}\Processors\NotificationsProcessor;

#[Module(
  providers: [
    NotificationsProcessor::class,
  ],
)]
final class AppModule
{
}
PHP);

  file_put_contents($workspace . '/src/Jobs/NotificationJob.php', <<<PHP
<?php

namespace {$namespace}\Jobs;

final class NotificationJob
{
  public function __construct(
    public readonly string \$message,
  ) {
  }
}
PHP);

  file_put_contents($workspace . '/src/Processors/NotificationsProcessor.php', <<<PHP
<?php

namespace {$namespace}\Processors;

use Assegai\Core\Attributes\Injectable;
use Assegai\Core\Queues\Attributes\QueueProcessor;
use {$namespace}\Jobs\NotificationJob;

#[Injectable]
#[QueueProcessor('sync.notifications')]
final class NotificationsProcessor
{
  public function process(NotificationJob \$job): void
  {
    file_put_contents(dirname(__DIR__, 2) . '/storage/processed.log', \$job->message . PHP_EOL, FILE_APPEND);
  }
}
PHP);

  file_put_contents($workspace . '/src/Queue/FakeQueueProcessResult.php', <<<PHP
<?php

namespace {$namespace}\Queue;

use Assegai\Common\Interfaces\Queues\QueueProcessResultInterface;
use Throwable;

final class FakeQueueProcessResult implements QueueProcessResultInterface
{
  /**
   * @param Throwable[] \$errors
   */
  public function __construct(
    private readonly mixed \$data,
    private readonly ?object \$job,
    private readonly array \$errors = [],
  ) {
  }

  public function getData(): mixed
  {
    return \$this->data;
  }

  public function isOk(): bool
  {
    return \$this->errors === [];
  }

  public function isError(): bool
  {
    return \$this->errors !== [];
  }

  public function getErrors(): array
  {
    return \$this->errors;
  }

  public function getNextError(): ?Throwable
  {
    return \$this->errors[0] ?? null;
  }

  public function getJob(): ?object
  {
    return \$this->job;
  }
}
PHP);

  file_put_contents($workspace . '/src/Queue/FakeQueue.php', <<<PHP
<?php

namespace {$namespace}\Queue;

use Assegai\Common\Interfaces\Queues\QueueInterface;
use Assegai\Common\Interfaces\Queues\QueueProcessResultInterface;
use Throwable;

final class FakeQueue implements QueueInterface
{
  /**
   * @param object[] \$jobs
   */
  public function __construct(
    private array \$jobs,
    private readonly string \$name,
  ) {
  }

  public static function create(array \$config): QueueInterface
  {
    return new self(
      jobs: \$config['jobs'] ?? [],
      name: (string) (\$config['name'] ?? 'default')
    );
  }

  public function add(object \$job, object|array|null \$options = null): void
  {
    \$this->jobs[] = \$job;
  }

  public function process(callable \$callback): QueueProcessResultInterface
  {
    \$job = array_shift(\$this->jobs);

    if (!\$job) {
      return new FakeQueueProcessResult(null, null);
    }

    try {
      \$callback(\$job);

      return new FakeQueueProcessResult(null, \$job);
    } catch (Throwable \$throwable) {
      return new FakeQueueProcessResult(null, \$job, [\$throwable]);
    }
  }

  public function getName(): string
  {
    return \$this->name;
  }

  public function getTotalJobs(): int
  {
    return count(\$this->jobs);
  }
}
PHP);

  return [
    'workspace' => $workspace,
    'processedLog' => $workspace . '/storage/processed.log',
    'processorClass' => $namespace . '\\Processors\\NotificationsProcessor',
  ];
}

function resolveAssegaiRepoRoot(): string
{
  $candidates = array_filter(array_unique([
    '/home/amasiye/development/atatusoft/projects/external/assegaiphp',
    '/home/amasiye/development/atatusoft/projects/internal/assegaiphp',
    realpath(dirname(__DIR__, 3)) ?: null,
    realpath(dirname(getcwd() ?: __DIR__)) ?: null,
  ]));

  foreach ($candidates as $candidate) {
    if (
      is_string($candidate) &&
      is_file($candidate . '/blog-api/vendor/autoload.php') &&
      is_file($candidate . '/core/src/Util/Functions.php')
    ) {
      return $candidate;
    }
  }

  throw new RuntimeException('Failed to locate the Assegai monorepo root for queue command tests.');
}

function deleteQueueWorkspace(string $directory): void
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

describe('Queue commands', function () {
  it('lists configured queues and discovered processors', function () {
    $fixture = createQueueWorkspace();

    try {
      $tester = new CommandTester(new QueueList());
      $status = $tester->execute([
        '--directory' => $fixture['workspace'],
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($tester->getDisplay())->toContain('sync.notifications');
      expect($tester->getDisplay())->toContain($fixture['processorClass']);
    } finally {
      deleteQueueWorkspace($fixture['workspace']);
    }
  });

  it('works a configured queue once with an auto-discovered processor', function () {
    $fixture = createQueueWorkspace();

    try {
      $tester = new CommandTester(new QueueWork());
      $status = $tester->execute([
        '--directory' => $fixture['workspace'],
        '--once' => true,
      ]);

      expect($status)->toBe(Command::SUCCESS);
      expect($tester->getDisplay())->toContain('Processed 1 job');
      expect(file_get_contents($fixture['processedLog']))->toContain('hello from queue');
    } finally {
      deleteQueueWorkspace($fixture['workspace']);
    }
  });
});
