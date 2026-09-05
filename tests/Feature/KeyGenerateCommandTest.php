<?php

use Assegai\Console\ApplicationFactory;
use Assegai\Console\Commands\KeyGenerate;
use Assegai\Console\Core\ApplicationSecretKey;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

function withKeyWorkspace(?string $env, ?string $example, Closure $test): void
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('assegai-key-', true);
  mkdir($workspace, 0700);
  file_put_contents($workspace . '/composer.json', '{}');
  file_put_contents($workspace . '/assegai.json', '{}');
  file_put_contents($workspace . '/bootstrap.php', '<?php');
  if ($env !== null) {
    file_put_contents($workspace . '/.env', $env);
  }
  if ($example !== null) {
    file_put_contents($workspace . '/.env.example', $example);
  }

  try {
    $test($workspace, new CommandTester(new KeyGenerate()));
  } finally {
    chmod($workspace, 0700);
    foreach (new DirectoryIterator($workspace) as $entry) {
      if (! $entry->isDot()) {
        unlink($entry->getPathname());
      }
    }
    rmdir($workspace);
  }
}

function generatedApplicationKey(string $workspace): string
{
  $contents = file_get_contents($workspace . '/.env') ?: '';
  preg_match('/^APP_SECRET_KEY=([a-f0-9]{64})$/m', $contents, $matches);
  expect($matches)->toHaveCount(2);
  return $matches[1] ?? '';
}

describe('key:generate', function () {
  it('initializes a cloned workspace from its example through the registered command', function () {
    $example = "APP_NAME=Clone\nAPP_SECRET_KEY=your_secret_key_here\nDATABASE_URL=sqlite:app.db\n";
    withKeyWorkspace(null, $example, function (string $workspace) use ($example) {
      // A cloned workspace does not need installed dependencies to generate a key.
      $application = ApplicationFactory::create($workspace, ['assegai', 'key:generate']);
      $tester = new CommandTester($application->find('key:generate'));
      expect($tester->execute(['--directory' => $workspace], ['interactive' => false]))->toBe(Command::SUCCESS);
      $key = generatedApplicationKey($workspace);
      expect(file_get_contents($workspace . '/.env'))->toBe(str_replace('your_secret_key_here', $key, $example))
        ->and(file_get_contents($workspace . '/.env.example'))->toBe($example)
        ->and($tester->getDisplay())->toContain('Created .env from .env.example.');
      expect($tester->getDisplay())->not->toContain($key);
      if (DIRECTORY_SEPARATOR === '/') {
        expect(fileperms($workspace . '/.env') & 0777)->toBe(0600);
      }
    });
  });

  it('initializes blank and scaffold-placeholder keys without confirmation', function (string $value) {
    withKeyWorkspace("APP_SECRET_KEY=$value\nAPP_NAME=Existing\n", null, function (string $workspace, CommandTester $tester) {
      expect($tester->execute(['--directory' => $workspace], ['interactive' => false]))->toBe(Command::SUCCESS);
      generatedApplicationKey($workspace);
      expect(file_get_contents($workspace . '/.env'))->toContain('APP_NAME=Existing');
    });
  })->with(['', '""', "''", 'your_secret_key_here', '"your_secret_key_here"', 'your-secret-key', '[YOUR_SECRET_KEY]']);

  it('appends a missing key without changing comments, prefixed names or multiline values', function () {
    $contents = "# APP_SECRET_KEY=example\r\nOLD_APP_SECRET_KEY=keep\r\nCERT=\"line one\r\nAPP_SECRET_KEY=inside-value\r\nline three\"\r\nAPP_NAME=Test";
    withKeyWorkspace($contents, null, function (string $workspace, CommandTester $tester) use ($contents) {
      expect($tester->execute(['--directory' => $workspace], ['interactive' => false]))->toBe(Command::SUCCESS);
      $updated = file_get_contents($workspace . '/.env') ?: '';
      expect($updated)->toStartWith($contents . "\r\n")
        ->toMatch('/\r\nAPP_SECRET_KEY=[a-f0-9]{64}\r\n$/');
    });
  });

  it('preserves dotenv formatting and the original file inode and access metadata', function () {
    $contents = "APP_NAME=Test\r\n  export APP_SECRET_KEY = \"old-key\"  # deployment key\r\nOTHER=value\r\n";
    withKeyWorkspace($contents, null, function (string $workspace, CommandTester $tester) use ($contents) {
      chmod($workspace . '/.env', 0640);
      $inode = fileinode($workspace . '/.env');
      $owner = fileowner($workspace . '/.env');
      $group = filegroup($workspace . '/.env');
      expect($tester->execute(['--directory' => $workspace, '--force' => true]))->toBe(Command::SUCCESS);
      $updated = file_get_contents($workspace . '/.env') ?: '';
      expect(preg_replace('/[a-f0-9]{64}/', '"old-key"', $updated))->toBe($contents);
      clearstatcache(true, $workspace . '/.env');
      expect(fileinode($workspace . '/.env'))->toBe($inode)
        ->and(fileowner($workspace . '/.env'))->toBe($owner)
        ->and(filegroup($workspace . '/.env'))->toBe($group)
        ->and(glob($workspace . '/.env.assegai-*'))->toBe([]);
      if (DIRECTORY_SEPARATOR === '/') {
        expect(fileperms($workspace . '/.env') & 0777)->toBe(0640);
      }
    });
  });

  it('refuses unattended rotation without changing the existing environment', function () {
    $contents = "APP_SECRET_KEY=existing-secret\nAPP_NAME=Test\n";
    withKeyWorkspace($contents, null, function (string $workspace, CommandTester $tester) use ($contents) {
      expect($tester->execute(['--directory' => $workspace], ['interactive' => false]))->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/.env'))->toBe($contents)
        ->and($tester->getDisplay())->toContain('Use --force');
      expect($tester->getDisplay())->not->toContain('existing-secret');
    });
  });

  it('leaves the existing key intact when rotation is declined', function () {
    $contents = "APP_SECRET_KEY=existing-secret\n";
    withKeyWorkspace($contents, null, function (string $workspace, CommandTester $tester) use ($contents) {
      $tester->setInputs(['no']);
      expect($tester->execute(['--directory' => $workspace], ['interactive' => true]))->toBe(Command::SUCCESS)
        ->and(file_get_contents($workspace . '/.env'))->toBe($contents)
        ->and($tester->getDisplay())->toContain('Application key unchanged.');
    });
  });

  it('rotates to fresh keys with interactive approval or explicit force', function () {
    withKeyWorkspace("APP_SECRET_KEY=existing-secret\n", null, function (string $workspace, CommandTester $tester) {
      $tester->setInputs(['yes']);
      expect($tester->execute(['--directory' => $workspace], ['interactive' => true]))->toBe(Command::SUCCESS);
      $first = generatedApplicationKey($workspace);
      expect($tester->getDisplay())->not->toContain($first);
      expect($tester->execute(['--directory' => $workspace, '--force' => true], ['interactive' => false]))->toBe(Command::SUCCESS);
      $second = generatedApplicationKey($workspace);
      expect($second)->not->toBe($first);
      expect($tester->getDisplay())->not->toContain($first);
      expect($tester->getDisplay())->not->toContain($second);
    });
  });

  it('fails without creating a partial file when neither environment file exists', function () {
    withKeyWorkspace(null, null, function (string $workspace, CommandTester $tester) {
      expect($tester->execute(['--directory' => $workspace]))->toBe(Command::FAILURE)
        ->and(file_exists($workspace . '/.env'))->toBeFalse()
        ->and($tester->getDisplay())->toContain('No readable .env or .env.example');
    });
  });

  it('rejects ambiguous or malformed dotenv without mutation', function (string $contents) {
    withKeyWorkspace($contents, null, function (string $workspace, CommandTester $tester) use ($contents) {
      expect($tester->execute(['--directory' => $workspace, '--force' => true]))->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/.env'))->toBe($contents);
    });
  })->with([
    "APP_SECRET_KEY=first\nAPP_SECRET_KEY=second\n",
    "APP_SECRET_KEY=\"unfinished\n",
    "OTHER=\"unfinished\nAPP_SECRET_KEY=hidden\n",
  ]);

  it('refuses to overwrite environment edits made while confirmation was pending', function () {
    withKeyWorkspace("APP_SECRET_KEY=before\n", null, function (string $workspace) {
      $key = ApplicationSecretKey::forWorkspace($workspace);
      $changed = "APP_SECRET_KEY=edited-elsewhere\n";
      file_put_contents($workspace . '/.env', $changed);
      expect(fn () => $key->generate())->toThrow(RuntimeException::class, '.env changed')
        ->and(file_get_contents($workspace . '/.env'))->toBe($changed)
        ->and(glob($workspace . '/.env.assegai-*'))->toBe([]);
    });
  });

  it('preserves a read-only environment on write failure', function () {
    $contents = "APP_SECRET_KEY=before\n";
    withKeyWorkspace($contents, null, function (string $workspace, CommandTester $tester) use ($contents) {
      chmod($workspace . '/.env', 0400);
      if (is_writable($workspace . '/.env')) {
        \PHPUnit\Framework\TestCase::markTestSkipped('The current user can write read-only files.');
      }
      expect($tester->execute(['--directory' => $workspace, '--force' => true]))->toBe(Command::FAILURE)
        ->and(file_get_contents($workspace . '/.env'))->toBe($contents);
    });
  });

  it('rejects non-workspace directories without creating secrets', function () {
    withKeyWorkspace(null, "APP_SECRET_KEY=\n", function (string $workspace, CommandTester $tester) {
      unlink($workspace . '/assegai.json');
      expect($tester->execute(['--directory' => $workspace]))->toBe(Command::FAILURE)
        ->and(file_exists($workspace . '/.env'))->toBeFalse();
    });
  });
});
