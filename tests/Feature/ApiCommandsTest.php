<?php

namespace Tests\Feature;

use Assegai\Console\Commands\Api\ApiClient;
use Assegai\Console\Commands\Api\ApiExport;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ApiCommandsTest extends TestCase
{
  private static string $workspace = '';

  public static function setUpBeforeClass(): void
  {
    self::$workspace = self::createApiWorkspace();
  }

  public static function tearDownAfterClass(): void
  {
    if (self::$workspace !== '') {
      self::deleteApiWorkspace(self::$workspace);
    }

    self::$workspace = '';
  }

  public function testItExportsAPostmanCollectionFromWorkspaceMetadata(): void
  {
    $command = new ApiExport();
    $tester = new CommandTester($command);
    $status = $tester->execute([
      'format' => 'postman',
      '--directory' => self::$workspace,
    ]);
    $outputFile = self::$workspace . '/generated/assegai.postman.collection.json';
    $contents = json_decode(file_get_contents($outputFile) ?: '', true);

    self::assertSame(Command::SUCCESS, $status);
    self::assertIsArray($contents);
    self::assertSame('Demo API API Collection', $contents['info']['name']);
    self::assertSame('Create Post', $contents['item'][0]['name']);
  }

  public function testItGeneratesATypeScriptClientFromWorkspaceMetadata(): void
  {
    $command = new ApiClient();
    $tester = new CommandTester($command);
    $status = $tester->execute([
      'language' => 'typescript',
      '--directory' => self::$workspace,
    ]);
    $outputFile = self::$workspace . '/generated/assegai-api-client.ts';
    $contents = file_get_contents($outputFile) ?: '';

    self::assertSame(Command::SUCCESS, $status);
    self::assertStringContainsString('createAssegaiClient', $contents);
    self::assertStringContainsString('postsCreate', $contents);
  }

  public function testItAlsoSupportsTypeScriptThroughApiExport(): void
  {
    $command = new ApiExport();
    $tester = new CommandTester($command);
    $status = $tester->execute([
      'format' => 'typescript',
      '--directory' => self::$workspace,
    ]);
    $outputFile = self::$workspace . '/generated/assegai-api-client.ts';
    $contents = file_get_contents($outputFile) ?: '';

    self::assertSame(Command::SUCCESS, $status);
    self::assertStringStartsWith('export function createAssegaiClient', trim($contents));
    self::assertStringContainsString('createAssegaiClient', $contents);
    self::assertStringContainsString('postsCreate', $contents);
    self::assertFalse(str_starts_with(trim($contents), '"'));
  }

  public function testItExportsFromANestedWorkspaceUsingCreateFromProjectBootstrap(): void
  {
    $nested = self::createNestedApiWorkspace();
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to resolve the current working directory.');
    }

    chdir(sys_get_temp_dir());

    try {
      $command = new ApiExport();
      $tester = new CommandTester($command);
      $status = $tester->execute([
        'format' => 'openapi',
        '--directory' => $nested['workspace'],
        '--output' => $nested['workspace'] . '/generated/nested-openapi.json',
      ]);

      $outputFile = $nested['workspace'] . '/generated/nested-openapi.json';
      $contents = json_decode(file_get_contents($outputFile) ?: '', true);

      self::assertSame(Command::SUCCESS, $status);
      self::assertIsArray($contents);
      self::assertSame('Nested Demo API API', $contents['info']['title']);
      self::assertSame('1.2.3', $contents['info']['version']);
    } finally {
      chdir($previousWorkingDirectory);
      self::deleteApiWorkspace($nested['root']);
    }
  }

  /**
   * @param array<string, mixed> $options
   */
  private static function createApiWorkspace(array $options = []): string
  {
    $workspace = $options['workspace'] ?? (sys_get_temp_dir() . '/' . uniqid('api-workspace-', true));
    $projectName = $options['projectName'] ?? 'Demo API';
    $composerName = $options['composerName'] ?? 'demo/api';
    $composerVersion = $options['composerVersion'] ?? '0.1.0';
    $moduleNamespace = $options['moduleNamespace'] ?? 'Demo';
    $moduleClass = $options['moduleClass'] ?? 'AppModule';
    $bootstrapFactoryMethod = $options['bootstrapFactoryMethod'] ?? 'create';
    $bootstrapFactoryArguments = $bootstrapFactoryMethod === 'createFromProject'
      ? sprintf('%s::class, __DIR__', $moduleClass)
      : sprintf('%s::class', $moduleClass);

    if (!mkdir($workspace . '/src', 0755, true) && !is_dir($workspace . '/src')) {
      throw new \RuntimeException("Failed to create workspace: $workspace");
    }

    mkdir($workspace . '/vendor', 0755, true);

    file_put_contents($workspace . '/assegai.json', json_encode([
        'name' => $projectName,
        'projectType' => 'project',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($workspace . '/composer.json', json_encode([
      'name' => $composerName,
      'autoload' => [
        'psr-4' => [
          $moduleNamespace . '\\' => 'src/',
        ],
      ],
      'require' => [
        'php' => '^8.3',
        'assegaiphp/core' => RECOMMENDED_CORE_VERSION_CONSTRAINT,
      ],
      'version' => $composerVersion,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($workspace . '/bootstrap.php', sprintf(<<<'PHP'
<?php

use Assegai\Core\AssegaiFactory;
use %s\%s;

$app = AssegaiFactory::%s(%s);
PHP, $moduleNamespace, $moduleClass, $bootstrapFactoryMethod, $bootstrapFactoryArguments));
    file_put_contents($workspace . '/src/' . $moduleClass . '.php', sprintf(<<<'PHP'
<?php

namespace %s;

class %s
{
}
PHP, $moduleNamespace, $moduleClass));
    file_put_contents($workspace . '/vendor/autoload.php', <<<'PHP'
<?php

require_once __DIR__ . '/stubs.php';
PHP);
    file_put_contents($workspace . '/vendor/stubs.php', sprintf(<<<'PHP'
<?php

namespace %s {
  if (!class_exists(%s)) {
    class %s {}
  }
}

namespace Assegai\Core {
  if (!class_exists(ControllerManager::class)) {
    class ControllerManager
    {
      public static function getInstance(): self
      {
        return new self();
      }
    }
  }

  if (!class_exists(ModuleManager::class)) {
    class ModuleManager
    {
      public static function getInstance(): self
      {
        return new self();
      }
    }
  }
}

namespace Assegai\Core\Http\Requests {
  if (!class_exists(Request::class)) {
    class Request
    {
      public static function getInstance(): self
      {
        return new self();
      }
    }
  }
}

namespace Assegai\Core\Config {
  if (!class_exists(ComposerConfig::class)) {
    class ComposerConfig
    {
      public function get(string $path): mixed
      {
        $filename = (getenv('ASSEGAI_WORKING_DIR') ?: getcwd()) . '/composer.json';
        $config = json_decode(file_get_contents($filename) ?: '', true);
        return $config[$path] ?? null;
      }
    }
  }

  if (!class_exists(ProjectConfig::class)) {
    class ProjectConfig
    {
      public function get(string $path): mixed
      {
        $filename = (getenv('ASSEGAI_WORKING_DIR') ?: getcwd()) . '/assegai.json';
        $config = json_decode(file_get_contents($filename) ?: '', true);
        return $config[$path] ?? null;
      }
    }
  }
}

namespace Assegai\Core\ApiDocs {
  if (!class_exists(OpenApiGenerator::class)) {
    class OpenApiGenerator
    {
      private object $composerConfig;
      private object $projectConfig;

      public function __construct(...$args)
      {
        $this->composerConfig = $args[3];
        $this->projectConfig = $args[4];
      }

      public function generate(string $rootModuleClass): array
      {
        $title = (($this->projectConfig->get('name') ?? 'Demo API') ?: 'Demo API') . ' API';
        $version = ($this->composerConfig->get('version') ?? '0.1.0') ?: '0.1.0';

        return [
          'openapi' => '3.1.0',
          'info' => [
            'title' => $title,
            'version' => $version,
          ],
          'servers' => [
            ['url' => 'http://localhost:5050'],
          ],
          'paths' => [
            '/posts' => [
              'post' => [
                'operationId' => 'postsCreate',
                'summary' => 'Create Post',
                'tags' => ['Posts'],
                'requestBody' => [
                  'content' => [
                    'application/json' => [
                      'schema' => ['$ref' => '#/components/schemas/CreatePostDTO'],
                      'example' => ['title' => 'Hello', 'body' => 'World'],
                    ],
                  ],
                ],
                'responses' => [
                  '201' => [
                    'description' => 'Created',
                    'content' => [
                      'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/PostDTO'],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
          'components' => [
            'schemas' => [
              'CreatePostDTO' => [
                'type' => 'object',
                'required' => ['title', 'body'],
                'properties' => [
                  'title' => ['type' => 'string'],
                  'body' => ['type' => 'string'],
                ],
              ],
              'PostDTO' => [
                'type' => 'object',
                'required' => ['id', 'title', 'body'],
                'properties' => [
                  'id' => ['type' => 'integer'],
                  'title' => ['type' => 'string'],
                  'body' => ['type' => 'string'],
                ],
              ],
            ],
          ],
        ];
      }
    }
  }

  if (!class_exists(PostmanCollectionGenerator::class)) {
    class PostmanCollectionGenerator
    {
      public function generate(array $document): array
      {
        return [
          'info' => ['name' => ($document['info']['title'] ?? 'Demo API') . ' Collection'],
          'item' => [['name' => 'Create Post']],
        ];
      }
    }
  }

  if (!class_exists(TypeScriptClientGenerator::class)) {
    class TypeScriptClientGenerator
    {
      public function generate(array $document): string
      {
        return "export function createAssegaiClient() { return { postsCreate() {} }; }\n";
      }
    }
  }
}
PHP, $moduleNamespace, var_export($moduleNamespace . '\\' . $moduleClass, true), $moduleClass));

    return $workspace;
  }

  /**
   * @return array{root: string, workspace: string}
   */
  private static function createNestedApiWorkspace(): array
  {
    $root = sys_get_temp_dir() . '/' . uniqid('api-workspace-root-', true);
    $workspace = $root . '/one/two/three/four/five/apilife-srv';

    self::createApiWorkspace([
      'workspace' => $workspace,
      'projectName' => 'Nested Demo API',
      'composerName' => 'nested/demo-api',
      'composerVersion' => '1.2.3',
      'moduleNamespace' => 'NestedDemo',
      'moduleClass' => 'RootApiModule',
      'bootstrapFactoryMethod' => 'createFromProject',
    ]);

    return [
      'root' => $root,
      'workspace' => $workspace,
    ];
  }

  private static function deleteApiWorkspace(string $directory): void
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
}
