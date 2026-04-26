<?php

use Assegai\Console\Installers\AbstractInstaller;
use Assegai\Console\Installers\DatabaseInstaller;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;

if (! function_exists('env')) {
  function env(string $key, mixed $default = null): mixed
  {
    return $default;
  }
}

function createNewProjectDefaultsWorkspace(): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('new-project-defaults-', true);

  if (! mkdir($workspace . '/config', 0755, true) && ! is_dir($workspace . '/config')) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  copy(__DIR__ . '/../../templates/config/secure.php', $workspace . '/config/secure.php');
  file_put_contents($workspace . '/composer.json', json_encode([
    'autoload' => [
      'psr-4' => [
        'Acme\\Saas\\' => 'src/',
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  return $workspace;
}

function deleteNewProjectDefaultsWorkspace(string $directory): void
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

describe('New project defaults', function () {
  it('forces ansi when generating the default users resource', function () {
    $installer = new class(
      new MockInput(),
      new MockOutput(),
      new FormatterHelper(),
      new QuestionHelper(),
      '/tmp/my project'
    ) extends DatabaseInstaller {
      public function install(): int
      {
        return Command::SUCCESS;
      }

      public function exposeGenerateResourceCommand(string $resourceName): string
      {
        return $this->buildGenerateResourceCommand($resourceName);
      }
    };

    $command = $installer->exposeGenerateResourceCommand('Users Admin');
    $expectedBinary = realpath(__DIR__ . '/../../bin/assegai');

    expect($command)->toContain(escapeshellarg(PHP_BINARY));
    expect($command)->toContain(escapeshellarg($expectedBinary ?: ''));
    expect($command)->toContain('--ansi generate resource');
    expect($command)->toContain(escapeshellarg('/tmp/my project'));
    expect($command)->toContain(escapeshellarg('Users Admin'));
  });

  it('uses loopback addresses for default database hosts', function () {
    expect(DEFAULT_MYSQL_HOST)->toBe('127.0.0.1');
    expect(DEFAULT_MARIADB_HOST)->toBe('127.0.0.1');
    expect(DEFAULT_POSTGRES_HOST)->toBe('127.0.0.1');
    expect(DEFAULT_MSSQL_HOST)->toBe('127.0.0.1');
  });

  it('ships secure config defaults for auth-sensitive settings', function () {
    $secureConfig = require __DIR__ . '/../../templates/config/secure.php';

    expect($secureConfig['databases'])->toBe([]);
    expect($secureConfig['authentication']['secret'])->toBe('your-secret-key');
    expect($secureConfig['authentication']['strategies'])->toBe([]);
    expect($secureConfig['authentication']['jwt']['entityClassName'])
      ->toBe('Assegai\\App\\Users\\Entities\\UserEntity');
  });

  it('syncs secure auth defaults without regex replacement interpolation warnings', function () {
    $workspace = createNewProjectDefaultsWorkspace();
    $warnings = [];

    try {
      $installer = new class(
        new MockInput(),
        new MockOutput(),
        new FormatterHelper(),
        new QuestionHelper(),
        $workspace
      ) extends DatabaseInstaller {
        public function syncForResource(string $resourceName): int
        {
          $this->userResourceName = $resourceName;

          return $this->syncSecureAuthenticationDefaults();
        }
      };

      set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
        if ($severity === E_WARNING || $severity === E_NOTICE || $severity === E_USER_WARNING) {
          $warnings[] = $message;
        }

        return true;
      });

      try {
        $status = $installer->syncForResource('Team Members');
      } finally {
        restore_error_handler();
      }

      $secureConfigContents = file_get_contents($workspace . '/config/secure.php') ?: '';
      $secureConfig = require $workspace . '/config/secure.php';

      expect($status)->toBe(Command::SUCCESS);
      expect($warnings)->toBe([]);
      expect($secureConfigContents)
        ->toContain("'entityClassName' => Acme\\Saas\\TeamMembers\\Entities\\TeamMemberEntity::class");
      expect($secureConfig['authentication']['jwt']['entityClassName'])
        ->toBe('Acme\\Saas\\TeamMembers\\Entities\\TeamMemberEntity');
    } finally {
      deleteNewProjectDefaultsWorkspace($workspace);
    }
  });

  it('offers module data_source enablement after configuring databases during project setup', function () {
    $installer = new class(
      new MockInput([], [], true),
      new MockOutput(),
      new FormatterHelper(),
      new QuestionHelper(),
      '/tmp/my project'
    ) extends DatabaseInstaller {
      /** @var string[] */
      public array $configuredDatabaseNames = [];

      protected function shouldConfigureDatabases(): bool
      {
        return true;
      }

      protected function selectDatabases(): array
      {
        return ['mariadb', 'mssql'];
      }

      protected function makeDatabaseInstaller(string $database): AbstractInstaller
      {
        return new class(
          $this->input,
          $this->output,
          $this->formatter,
          $this->questionHelper,
          $this->projectPath
        ) extends AbstractInstaller {
          public function install(): int
          {
            $this->configuredDatabaseName = 'blog';
            return Command::SUCCESS;
          }
        };
      }

      protected function checkForMissingExtensions(array $extensions): array
      {
        return [];
      }

      protected function ensureDefaultUserResource(): int
      {
        return Command::SUCCESS;
      }

      protected function syncSecureAuthenticationDefaults(): int
      {
        return Command::SUCCESS;
      }

      protected function installOrmPackage(): int
      {
        return Command::SUCCESS;
      }

      protected function configureModuleDataSources(array $configuredDatabaseNames): int
      {
        $this->configuredDatabaseNames = $configuredDatabaseNames;
        return Command::SUCCESS;
      }
    };

    expect($installer->install())->toBe(Command::SUCCESS);
    expect($installer->configuredDatabaseNames)->toBe(['mariadb:blog', 'mssql:blog']);
  });

  it('prints the database installation completion message without a literal newline marker', function () {
    $output = new class extends MockOutput {
      /**
       * @return array<int, string>
       */
      public function getBuffer(): array
      {
        return $this->buffer;
      }

      public function isSilent(): bool
      {
        return parent::isSilent();
      }
    };

    $installer = new class(
      new MockInput([], [], true),
      $output,
      new FormatterHelper(),
      new QuestionHelper(),
      '/tmp/my project'
    ) extends DatabaseInstaller {
      protected function shouldConfigureDatabases(): bool
      {
        return true;
      }

      protected function selectDatabases(): array
      {
        return ['sqlite'];
      }

      protected function makeDatabaseInstaller(string $database): AbstractInstaller
      {
        return new class(
          $this->input,
          $this->output,
          $this->formatter,
          $this->questionHelper,
          $this->projectPath
        ) extends AbstractInstaller {
          public function install(): int
          {
            $this->configuredDatabaseName = 'blog';
            return Command::SUCCESS;
          }
        };
      }

      protected function checkForMissingExtensions(array $extensions): array
      {
        return [];
      }

      protected function ensureDefaultUserResource(): int
      {
        return Command::SUCCESS;
      }

      protected function syncSecureAuthenticationDefaults(): int
      {
        return Command::SUCCESS;
      }

      protected function installOrmPackage(): int
      {
        return Command::SUCCESS;
      }

      protected function configureModuleDataSources(array $configuredDatabaseNames): int
      {
        return Command::SUCCESS;
      }
    };

    expect($installer->install())->toBe(Command::SUCCESS);

    $rendered = implode("\n", $output->getBuffer());

    expect($rendered)->toContain('Database installation complete');
    expect(str_contains($rendered, 'Database installation complete\n'))->toBeFalse();
  });

  it('ships a front controller that short-circuits safe public assets before bootstrapping PHP routing', function () {
    $frontController = file_get_contents(__DIR__ . '/../../templates/index.php');

    expect($frontController)
      ->toContain("realpath(__DIR__ . '/public')")
      ->toContain('X-Content-Type-Options: nosniff')
      ->toContain("PHP_SAPI === 'cli-server'")
      ->toContain("realpath(" . '$_SERVER' . "['DOCUMENT_ROOT'] ?? '')")
      ->toContain('return false;')
      ->toContain('assegai_stream_public_asset($assetPath);')
      ->toContain('readfile($assetPath);')
      ->toContain("\$allowedExtensions = [")
      ->toContain("return str_starts_with(\$normalizedRelativePath, '.well-known/');")
      ->toContain("'css' => 'text/css'")
      ->toContain("'js', 'mjs' => 'text/javascript'")
      ->toContain("\$segment === '.well-known'");
  });

  it('ships a starter view that renders props without declaring them', function () {
    $starterView = file_get_contents(__DIR__ . '/../../templates/src/Views/index.php');

    expect(str_contains($starterView ?: '', '$projectName ='))->toBeFalse();
    expect(str_contains($starterView ?: '', '$title ='))->toBeFalse();
    expect(str_contains($starterView ?: '', '$titleNote ='))->toBeFalse();
    expect(str_contains($starterView ?: '', '$status ='))->toBeFalse();
    expect(str_contains($starterView ?: '', '$summary ='))->toBeFalse();
    expect(str_contains($starterView ?: '', '??'))->toBeFalse();
    expect($starterView)
      ->toContain("htmlspecialchars(" . '$title' . ", ENT_QUOTES, 'UTF-8')")
      ->toContain("htmlspecialchars(" . '$guideLink' . ", ENT_QUOTES, 'UTF-8')");
});
});
