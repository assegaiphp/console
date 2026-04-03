<?php

use Assegai\Console\Commands\Queue\QueueList;
use Assegai\Console\Commands\Queue\QueueWork;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @return array{workspace: string, processedLog: string, processorClass: string}
 */
function createQueueWorkspace(): array
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('queue-command-', true);
  $suffix = preg_replace('/[^A-Za-z0-9]/', '', uniqid('', true));
  $namespace = "Assegaiphp\\QueueTest{$suffix}";
  $vendorAutoload = resolveConsoleVendorAutoload();
  $stubsFile = $workspace . '/vendor/stubs.php';

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

  $autoload = <<<'AUTOLOAD'
<?php

$prefix = %s;
$baseDir = %s;

spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
  if (!str_starts_with($class, $prefix)) {
    return;
  }

  $relative = substr($class, strlen($prefix));
  $file = rtrim($baseDir, '/\\') . '/' . str_replace('\\', '/', $relative) . '.php';

  if (is_file($file)) {
    require_once $file;
  }
}, prepend: true);

require %s;
require_once %s;

return true;
AUTOLOAD;

  file_put_contents($stubsFile, <<<'STUBS'
<?php

namespace Assegai\Core\Attributes {
  if (!class_exists(Injectable::class)) {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    final class Injectable
    {
    }
  }
}

namespace Assegai\Core\Attributes\Modules {
  if (!class_exists(Module::class)) {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    final class Module
    {
      public function __construct(
        public array $providers = [],
        public array $controllers = [],
        public array $imports = [],
        public array $exports = [],
        public array $config = [],
        public array $declarations = [],
      ) {
      }
    }
  }
}

namespace Assegai\Core\Queues\Attributes {
  if (!class_exists(QueueProcessor::class)) {
    #[\Attribute(\Attribute::TARGET_CLASS)]
    final class QueueProcessor
    {
      public function __construct(
        public string $path,
        public string $method = 'process',
      ) {
      }
    }
  }
}

namespace Assegai\Core\Config {
  if (!class_exists(AppConfig::class)) {
    final class AppConfig {}
  }

  if (!class_exists(ComposerConfig::class)) {
    final class ComposerConfig {}
  }

  if (!class_exists(ProjectConfig::class)) {
    final class ProjectConfig {}
  }
}

namespace Assegai\Core\Http\Requests {
  if (!class_exists(Request::class)) {
    final class Request
    {
      private static ?self $instance = null;

      public static function getInstance(): self
      {
        return self::$instance ??= new self();
      }
    }
  }
}

namespace Assegai\Core\Http\Responses {
  if (!class_exists(Response::class)) {
    final class Response
    {
      private static ?self $instance = null;

      public static function getInstance(): self
      {
        return self::$instance ??= new self();
      }
    }
  }
}

namespace Assegai\Core {
  if (!class_exists(Injector::class)) {
    final class Injector
    {
      private static ?self $instance = null;

      /** @var array<string, mixed> */
      private array $entries = [];

      public static function getInstance(): self
      {
        return self::$instance ??= new self();
      }

      public function add(string $id, mixed $dependency): void
      {
        $this->entries[$id] = $dependency;
      }

      public function resolve(string $class): mixed
      {
        return new $class();
      }
    }
  }

  if (!class_exists(ModuleManager::class)) {
    final class ModuleManager
    {
      private static ?self $instance = null;

      public static function getInstance(): self
      {
        return self::$instance ??= new self();
      }
    }
  }
}
STUBS);

  file_put_contents(
    $workspace . '/vendor/autoload.php',
    sprintf(
      $autoload,
      var_export($namespace . '\\', true),
      var_export($workspace . '/src/', true),
      var_export($vendorAutoload, true),
      var_export($stubsFile, true),
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

AssegaiFactory::createFromProject(AppModule::class, __DIR__);
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

function resolveConsoleVendorAutoload(): string
{
  $repoRoot = realpath(__DIR__ . '/../../');

  if (!is_string($repoRoot)) {
    throw new RuntimeException('Failed to resolve the console repository root for queue command tests.');
  }

  $autoloadFile = $repoRoot . '/vendor/autoload.php';

  if (!is_file($autoloadFile)) {
    throw new RuntimeException('Failed to locate the console Composer autoloader for queue command tests.');
  }

  return $autoloadFile;
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
