<?php

use Assegai\Console\Tests\Feature\Support\VersionWorkspaceFactory;

/** @return array{root: string, workspace: string, autoload: string, entrypoint: string} */
function runningCliFixture(string $layout, string $projectVersion = '0.10.3'): array
{
  $root = sys_get_temp_dir() . '/' . uniqid('console-running-version-', true);
  $repository = dirname(__DIR__, 2);
  $workspace = $root . '/project';
  $vendor = $root . '/global/' . ($layout === 'custom-vendor' ? 'libraries' : 'vendor');
  $installation = $layout === 'checkout' ? $root . '/console' : $vendor . '/assegaiphp/console';
  if ($layout === 'checkout') {
    $vendor = $installation . '/vendor';
  }

  foreach ([$installation, $vendor . '/composer', $workspace . '/vendor/composer'] as $directory) {
    if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
      throw new RuntimeException("Failed to create $directory");
    }
  }

  foreach (['src', 'bin', 'assets'] as $directory) {
    if (!copy_directory($repository . '/' . $directory, $installation . '/' . $directory)) {
      throw new RuntimeException("Failed to copy $directory");
    }
  }

  $metadata = [
    'root' => ['name' => $layout === 'checkout' ? PACKAGE_NAME_CLI : 'fixture/global',
      'pretty_version' => '0.10.4', 'install_path' => $installation],
    'versions' => [PACKAGE_NAME_CLI => [
      'pretty_version' => '0.10.4', 'version' => '0.10.4.0', 'install_path' => $installation,
    ]],
  ];
  file_put_contents($vendor . '/composer/installed.php', '<?php return ' . var_export($metadata, true) . ';');
  $metadata['root'] = ['name' => 'fixture/project', 'pretty_version' => 'dev-main', 'install_path' => $workspace];
  $metadata['versions'][PACKAGE_NAME_CLI]['pretty_version'] = $projectVersion;
  $metadata['versions'][PACKAGE_NAME_CLI]['install_path'] = $workspace;
  file_put_contents($workspace . '/vendor/composer/installed.php', '<?php return ' . var_export($metadata, true) . ';');

  // Use Composer's real loader and metadata reader without installing dependencies.
  $loaderSource = var_export($repository . '/vendor/composer/ClassLoader.php', true);
  $versionsSource = var_export($repository . '/vendor/composer/InstalledVersions.php', true);
  $dependencyMap = var_export(require $repository . '/vendor/composer/autoload_psr4.php', true);
  $classMap = var_export(require $repository . '/vendor/composer/autoload_classmap.php', true);
  $installationSource = var_export($installation, true);
  $cliNamespace = var_export('Assegai\\Console\\', true);
  file_put_contents($vendor . '/autoload.php', <<<PHP
<?php
require_once $loaderSource;
require_once $versionsSource;
\$loader = new Composer\Autoload\ClassLoader(__DIR__);
foreach ($dependencyMap as \$namespace => \$paths) {
  \$loader->setPsr4(\$namespace, \$paths);
}
\$loader->addClassMap($classMap);
\$loader->setPsr4($cliNamespace, $installationSource . '/src');
\$loader->register(true);
require_once $installationSource . '/src/Util/Constants.php';
require_once $installationSource . '/src/Util/Functions.php';
return \$loader;
PHP);
  file_put_contents($workspace . '/vendor/autoload.php', <<<PHP
<?php
require_once $loaderSource;
\$loader = new Composer\Autoload\ClassLoader(__DIR__);
\$loader->register(true);
return \$loader;
PHP);

  $entrypoint = $installation . '/bin/assegai';
  if ($layout === 'proxy') {
    mkdir($vendor . '/bin');
    $entrypoint = $vendor . '/bin/assegai';
    file_put_contents($entrypoint, '<?php require __DIR__ . "/../assegaiphp/console/bin/assegai";');
  } elseif ($layout === 'symlink') {
    $link = $root . '/assegai';
    if (!symlink($entrypoint, $link)) {
      throw new RuntimeException('Failed to link the CLI entrypoint.');
    }
    $entrypoint = $link;
  }

  return ['root' => $root, 'workspace' => $workspace, 'autoload' => $vendor . '/autoload.php', 'entrypoint' => $entrypoint];
}

/**
 * @param array{workspace: string} $fixture
 * @param list<string> $command
 */
function runCliVersionProcess(array $fixture, array $command): string
{
  $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes,
    $fixture['workspace'], ['ASSEGAI_NO_UPDATE_CHECK' => '1']);
  if (!is_resource($process)) {
    throw new RuntimeException('Failed to start CLI version probe.');
  }
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $status = proc_close($process);
  expect($status)->toBe(0, (string) $stderr);
  return (string) $stdout;
}

it('keeps the executing installation version across CLI entrypoints and autoload order', function (string $layout, bool $projectFirst) {
  $fixture = runningCliFixture($layout);
  try {
    foreach (['--version', 'version', 'v', 'info', 'i', ''] as $command) {
      $arguments = [PHP_BINARY, $fixture['entrypoint'], $command];
      if ($projectFirst) {
        $code = 'require ' . var_export($fixture['workspace'] . '/vendor/autoload.php', true)
          . '; require ' . var_export($fixture['entrypoint'], true) . ';';
        $arguments = [PHP_BINARY, '-r', $code, '--', $command];
      }
      $output = runCliVersionProcess($fixture, $arguments);
      expect($output)->toContain('0.10.4');
      expect(str_contains($output, '0.10.3'))->toBeFalse();
    }
  } finally {
    VersionWorkspaceFactory::remove($fixture['root']);
  }
})->with(['checkout', 'dependency', 'proxy', 'custom-vendor', 'symlink'])->with([false, true]);

it('uses the executing version for inspectors, update checks and framework recommendations', function () {
  $fixture = runningCliFixture('dependency', '0.9.5');
  try {
    $autoload = var_export($fixture['autoload'], true);
    $projectAutoload = var_export($fixture['workspace'] . '/vendor/autoload.php', true);
    $output = runCliVersionProcess($fixture, [PHP_BINARY, '-r', <<<PHP
require $projectAutoload;
require $autoload;
require $projectAutoload;
\$inspector = new Assegai\Console\Util\Inspector(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput());
echo json_encode([
  \$inspector->getCLIVersion(),
  \$inspector->getPackageVersion(PACKAGE_NAME_CLI),
  Assegai\Console\Util\CliVersionChecker::forRunningCli()->getCurrentVersion(),
  recommended_framework_release_line(),
  RECOMMENDED_FRAMEWORK_RELEASE_LINE,
]);
PHP]);
    expect(json_decode($output, true))->toBe(['0.10.4', '0.10.4', '0.10.4', '^0.10.0', '^0.10.0']);
  } finally {
    VersionWorkspaceFactory::remove($fixture['root']);
  }
});

it('preserves root metadata and normalized versions without borrowing another installation', function (string $metadataCase, string $expected) {
  $fixture = runningCliFixture('checkout');
  $alias = $fixture['root'] . '/installation-link';
  try {
    $metadataFile = dirname($fixture['autoload']) . '/composer/installed.php';
    $metadata = require $metadataFile;
    if ($metadataCase === 'root') {
      unset($metadata['versions'][PACKAGE_NAME_CLI]);
    } elseif ($metadataCase === 'normalized') {
      unset($metadata['versions'][PACKAGE_NAME_CLI]['pretty_version'], $metadata['root']['pretty_version']);
    } elseif ($metadataCase === 'symlink') {
      expect(symlink($metadata['root']['install_path'], $alias))->toBeTrue();
      $metadata['versions'][PACKAGE_NAME_CLI]['install_path'] = $alias;
      $metadata['root']['install_path'] = $alias;
    } else {
      $metadata['versions'][PACKAGE_NAME_CLI]['install_path'] = $fixture['workspace'];
      $metadata['root']['name'] = 'fixture/unrelated';
    }
    file_put_contents($metadataFile, '<?php return ' . var_export($metadata, true) . ';');
    $output = runCliVersionProcess($fixture, [PHP_BINARY, $fixture['entrypoint'], 'version']);
    expect($output)->toContain('CLI Version: ' . $expected);
    expect(str_contains($output, '0.10.3'))->toBeFalse();
  } finally {
    if (is_link($alias)) {
      unlink($alias);
    }
    VersionWorkspaceFactory::remove($fixture['root']);
  }
})->with([
  ['root', '0.10.4'],
  ['normalized', '0.10.4.0'],
  ['symlink', '0.10.4'],
  ['unrelated', 'Unknown'],
]);
