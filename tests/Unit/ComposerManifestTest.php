<?php

use Assegai\Console\Util\ComposerManifest;

/**
 * @param array<string, mixed> $composerConfig
 */
function createComposerManifestWorkspace(array $composerConfig): string
{
  $workspace = sys_get_temp_dir() . '/' . uniqid('composer-manifest-', true);

  if (! mkdir($workspace, 0755, true) && ! is_dir($workspace)) {
    throw new RuntimeException("Failed to create test workspace: $workspace");
  }

  file_put_contents(
    $workspace . '/composer.json',
    json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
  );

  return $workspace;
}

function deleteComposerManifestWorkspace(string $directory): void
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

describe('ComposerManifest', function () {
  it('uses Composer intervals to upgrade older ranges and preserve narrower compatible ranges', function (string $existing, string $expected) {
    $manifest = ['require' => ['assegaiphp/core' => $existing]];
    expect(ComposerManifest::ensureRecommendedRequirement($manifest, 'assegaiphp/core', '~0.11.1')['require']['assegaiphp/core'])->toBe($expected);
  })->with([
    ['^0.10.2', '~0.11.1'], ['0.10.2', '~0.11.1'], ['^0.11.0', '~0.11.1'],
    ['>=0.11.2 <0.11.4', '>=0.11.2 <0.11.4'], ['~0.11.9', '~0.11.9'], ['^0.12.0', '^0.12.0'],
  ]);

  it('requires review for ambiguous custom ranges and development branches', function (string $constraint) {
    expect(fn() => ComposerManifest::ensureRecommendedRequirement(['require' => ['assegaiphp/core' => $constraint]], 'assegaiphp/core', '~0.11.1'))
      ->toThrow(RuntimeException::class, 'review');
  })->with(['^0.10 || ^0.12', '>=0.10.2 <0.11.5', 'dev-main']);

  it('resolves the workspace source namespace from composer autoload metadata', function () {
    $workspace = createComposerManifestWorkspace([
      'autoload' => [
        'psr-4' => [
          'Acme\\Shared\\' => 'lib/',
          'Acme\\ClonedApp\\' => 'src/',
        ],
      ],
    ]);

    try {
      expect(ComposerManifest::resolvePsr4Namespace($workspace))->toBe('Acme\\ClonedApp');
    } finally {
      deleteComposerManifestWorkspace($workspace);
    }
  });

  it('resolves the workspace source namespace from composer PSR-4 directory arrays', function () {
    $workspace = createComposerManifestWorkspace([
      'autoload' => [
        'psr-4' => [
          'Acme\\Shared\\' => ['lib/', 'packages/shared/'],
          'Acme\\ArrayApp\\' => ['generated/', './src/'],
        ],
      ],
    ]);

    try {
      expect(ComposerManifest::resolvePsr4Namespace($workspace))->toBe('Acme\\ArrayApp');
    } finally {
      deleteComposerManifestWorkspace($workspace);
    }
  });

  it('falls back when composer metadata cannot resolve a source namespace', function () {
    $workspace = createComposerManifestWorkspace([
      'autoload' => [
        'psr-4' => [
          'Acme\\Shared\\' => 'lib/',
        ],
      ],
    ]);

    try {
      expect(ComposerManifest::resolvePsr4Namespace($workspace))->toBe('Assegai\\App');
      expect(ComposerManifest::resolvePsr4Namespace('/missing-workspace', 'Fallback\\App'))->toBe('Fallback\\App');
    } finally {
      deleteComposerManifestWorkspace($workspace);
    }
  });
});
