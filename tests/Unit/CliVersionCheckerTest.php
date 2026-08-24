<?php

use Assegai\Console\Util\CliVersionChecker;

function versionCheckerCacheFile(): string
{
  return sys_get_temp_dir() . '/' . uniqid('assegai-version-check-', true) . '/version.json';
}

function removeVersionCheckerCache(string $cacheFile): void
{
  if (is_file($cacheFile)) {
    unlink($cacheFile);
  }

  $directory = dirname($cacheFile);

  if (is_dir($directory)) {
    rmdir($directory);
  }
}

function versionMetadata(string ...$versions): string
{
  return (string) json_encode([
    'packages' => [
      PACKAGE_NAME_CLI => array_map(
        static fn (string $version): array => ['version' => $version],
        $versions,
      ),
    ],
  ], JSON_UNESCAPED_SLASHES);
}

describe('CliVersionChecker', function () {
  it('selects the newest stable release and reports an available update', function () {
    $cacheFile = versionCheckerCacheFile();

    try {
      $checker = new CliVersionChecker(
        '0.9.5',
        $cacheFile,
        static fn (string $url): string => versionMetadata('dev-develop', '0.10.0-RC1', 'v0.9.5', '0.10.1', '0.10.0'),
        static fn (): int => 1000,
      );

      expect($checker->getLatestVersion())->toBe('0.10.1')
        ->and($checker->findAvailableUpdate())->toBe('0.10.1');
    } finally {
      removeVersionCheckerCache($cacheFile);
    }
  });

  it('reuses a successful check for twenty four hours', function () {
    $cacheFile = versionCheckerCacheFile();
    $requests = 0;

    try {
      $checker = new CliVersionChecker(
        '0.10.0',
        $cacheFile,
        static function (string $url) use (&$requests): string {
          $requests++;
          return versionMetadata('0.10.1');
        },
        static fn (): int => 1000,
      );

      expect($checker->getLatestVersion())->toBe('0.10.1')
        ->and($checker->getLatestVersion())->toBe('0.10.1')
        ->and($requests)->toBe(1);
    } finally {
      removeVersionCheckerCache($cacheFile);
    }
  });

  it('caches failed checks without blocking normal CLI execution', function () {
    $cacheFile = versionCheckerCacheFile();
    $requests = 0;

    try {
      $checker = new CliVersionChecker(
        '0.10.0',
        $cacheFile,
        static function (string $url) use (&$requests): false {
          $requests++;
          return false;
        },
        static fn (): int => 1000,
      );

      expect($checker->findAvailableUpdate())->toBeNull()
        ->and($checker->findAvailableUpdate())->toBeNull()
        ->and($requests)->toBe(1);
    } finally {
      removeVersionCheckerCache($cacheFile);
    }
  });

  it('skips remote checks for a non-release development version', function () {
    $cacheFile = versionCheckerCacheFile();
    $requests = 0;

    try {
      $checker = new CliVersionChecker(
        'dev-develop',
        $cacheFile,
        static function (string $url) use (&$requests): string {
          $requests++;
          return versionMetadata('0.10.1');
        },
      );

      expect($checker->findAvailableUpdate())->toBeNull()
        ->and($requests)->toBe(0);
    } finally {
      removeVersionCheckerCache($cacheFile);
    }
  });
});
