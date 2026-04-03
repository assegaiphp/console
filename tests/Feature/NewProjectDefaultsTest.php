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
    $defaultConfig = require __DIR__ . '/../../templates/config/default.php';

    expect(DEFAULT_MYSQL_HOST)->toBe('127.0.0.1');
    expect(DEFAULT_POSTGRES_HOST)->toBe('127.0.0.1');
    expect($defaultConfig['databases']['mysql']['db_name']['host'])->toBe('127.0.0.1');
    expect($defaultConfig['databases']['pgsql']['db_name']['host'])->toBe('127.0.0.1');
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
        return ['mysql'];
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
    expect($installer->configuredDatabaseNames)->toBe(['mysql:blog']);
  });
});
