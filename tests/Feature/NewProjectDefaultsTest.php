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
    expect($rendered)->not->toContain('Database installation complete\\n');
  });

  it('ships a front controller that short-circuits safe public assets before bootstrapping PHP routing', function () {
    $frontController = file_get_contents(__DIR__ . '/../../templates/index.php');

    expect($frontController)
      ->toContain("realpath(__DIR__ . '/public')")
      ->toContain('X-Content-Type-Options: nosniff')
      ->toContain('readfile($assetPath);')
      ->toContain("\$allowedExtensions = [")
      ->toContain("!str_starts_with(\$normalizedRelativePath, '.well-known/')")
      ->toContain('!$shouldBypassStreaming')
      ->toContain("\$segment === '.well-known'");
  });
});
