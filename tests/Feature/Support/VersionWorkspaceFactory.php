<?php

namespace Assegai\Console\Tests\Feature\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class VersionWorkspaceFactory
{
  public static function create(string $frameworkVersion, string $source = 'installed'): string
  {
    $workspace = sys_get_temp_dir() . '/' . uniqid('console-version-', true);

    if (!mkdir($workspace, 0755, true) && !is_dir($workspace)) {
      throw new RuntimeException("Failed to create test workspace: $workspace");
    }

    file_put_contents($workspace . '/composer.json', json_encode([
      'name' => 'assegai/test-app',
      'require' => [
        'php' => '^8.3',
        'assegaiphp/core' => '^0.8.0',
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($workspace . '/assegai.json', json_encode([
      'name' => 'test-app',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($workspace . '/bootstrap.php', <<<'PHP_BOOTSTRAP'
<?php

use Assegai\App\AppModule;
use Assegai\Core\AssegaiFactory;

function bootstrap(): void
{
  AssegaiFactory::createFromProject(AppModule::class, __DIR__)->run();
}
PHP_BOOTSTRAP);

    if ($source === 'installed') {
      $composerDirectory = $workspace . '/vendor/composer';

      if (!mkdir($composerDirectory, 0755, true) && !is_dir($composerDirectory)) {
        throw new RuntimeException("Failed to create composer metadata directory: $composerDirectory");
      }

      file_put_contents($composerDirectory . '/installed.json', json_encode([
        'packages' => [
          [
            'name' => 'assegaiphp/core',
            'version' => $frameworkVersion,
          ],
        ],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    if ($source === 'lock') {
      file_put_contents($workspace . '/composer.lock', json_encode([
        'packages' => [
          [
            'name' => 'assegaiphp/core',
            'version' => $frameworkVersion,
          ],
        ],
        'packages-dev' => [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    return $workspace;
  }

  public static function remove(string $directory): void
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
