<?php

namespace Tests\Feature;

use Assegai\Console\Commands\Api\ApiClient;
use Assegai\Console\Commands\Api\ApiExport;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
    self::assertSame('Demo API Collection', $contents['info']['name']);
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

  private static function createApiWorkspace(): string
  {
    $workspace = sys_get_temp_dir() . '/' . uniqid('api-workspace-', true);

    if (!mkdir($workspace . '/src', 0755, true) && !is_dir($workspace . '/src')) {
      throw new \RuntimeException("Failed to create workspace: $workspace");
    }

    mkdir($workspace . '/vendor', 0755, true);

    file_put_contents($workspace . '/assegai.json', json_encode([
      'name' => 'Demo API',
      'projectType' => 'project',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($workspace . '/composer.json', json_encode([
      'name' => 'demo/api',
      'autoload' => [
        'psr-4' => [
          'Demo\\' => 'src/',
        ],
      ],
      'require' => [
        'php' => '>=8.3',
        'assegaiphp/core' => '^0.7.0',
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents($workspace . '/bootstrap.php', <<<'PHP'
<?php

use Assegai\Core\AssegaiFactory;
use Demo\AppModule;

$app = AssegaiFactory::create(AppModule::class);
PHP);
    file_put_contents($workspace . '/src/AppModule.php', <<<'PHP'
<?php

namespace Demo;

class AppModule
{
}
PHP);
    file_put_contents($workspace . '/vendor/autoload.php', <<<'PHP'
<?php

require_once __DIR__ . '/stubs.php';
PHP);
    file_put_contents($workspace . '/vendor/stubs.php', <<<'PHP'
<?php

namespace Demo {
  class AppModule {}
}

namespace Assegai\Core {
  class ControllerManager
  {
    public static function getInstance(): self
    {
      return new self();
    }
  }

  class ModuleManager
  {
    public static function getInstance(): self
    {
      return new self();
    }
  }
}

namespace Assegai\Core\Http\Requests {
  class Request
  {
    public static function getInstance(): self
    {
      return new self();
    }
  }
}

namespace Assegai\Core\Config {
  class ComposerConfig {}
  class ProjectConfig {}
}

namespace Assegai\Core\ApiDocs {
  class OpenApiGenerator
  {
    public function __construct(...$args)
    {
    }

    public function generate(string $rootModuleClass): array
    {
      return [
        'openapi' => '3.1.0',
        'info' => [
          'title' => 'Demo API',
          'version' => '0.1.0',
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

  class PostmanCollectionGenerator
  {
    public function generate(array $document): array
    {
      return [
        'info' => ['name' => 'Demo API Collection'],
        'item' => [['name' => 'Create Post']],
      ];
    }
  }

  class TypeScriptClientGenerator
  {
    public function generate(array $document): string
    {
      return "export function createAssegaiClient() { return { postsCreate() {} }; }\n";
    }
  }
}
PHP);

    return $workspace;
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
