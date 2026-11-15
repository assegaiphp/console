#!/usr/bin/env php
<?php

declare(strict_types=1);

// Standalone entrypoint: no installed Console, application or first-party graph required.
$result = ['schema' => 1, 'status' => 'blocked', 'issues' => []];
try {
  $options = [];
  $allowed = ['mode', 'inventory', 'modules', 'manifest', 'evidence', 'website-manifest', 'website-lock', 'semver-autoload', 'format', 'console-root', 'console-ref', 'core-root', 'core-ref', 'ref'];
  foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/\A--([a-z-]+)=(.+)\z/D', $argument, $match) !== 1 || !in_array($match[1], $allowed, true)
      || isset($options[$match[1]]) || preg_match('/[\x00-\x1f\x7f]/', $match[2])) {
      throw new RuntimeException('Use unique named --option=value arguments only.');
    }
    $options[$match[1]] = $match[2];
  }
  foreach (['mode', 'inventory', 'modules', 'semver-autoload'] as $required) {
    if (!isset($options[$required])) { throw new RuntimeException('--' . $required . ' is required.'); }
  }
  if (($options['format'] ?? 'json') !== 'json' || !in_array($options['mode'], ['package', 'consumer', 'prepare', 'publish'], true)) {
    throw new RuntimeException('Choose --mode=package|consumer|prepare|publish and --format=json.');
  }
  $autoload = realpath($options['semver-autoload']);
  if ($autoload === false || !is_file($autoload) || !is_readable($autoload)) {
    throw new RuntimeException('--semver-autoload must identify the explicitly supplied Composer semver autoloader.');
  }
  require_once $autoload;
  spl_autoload_register(static function (string $class): void {
    foreach (['Assegai\\Console\\Releases\\' => 'Releases/', 'Assegai\\Console\\Util\\' => 'Util/'] as $prefix => $directory) {
      if (str_starts_with($class, $prefix)) {
        $name = substr($class, strlen($prefix));
        if (preg_match('/\A[A-Za-z][A-Za-z0-9]*\z/', $name) === 1 && is_file($path = __DIR__ . '/../src/' . $directory . $name . '.php')) {
          require_once $path;
        }
      }
    }
  }, prepend: true);
  if ((new ReflectionClass(Assegai\Console\Releases\ReleaseInventoryValidator::class))->getFileName() !== realpath(__DIR__ . '/../src/Releases/ReleaseInventoryValidator.php')) {
    throw new RuntimeException('The supplied autoloader has already loaded a different Console release validator.');
  }
  $reader = new Assegai\Console\Releases\ReleaseInventoryValidator();
  $sourcePins = null;
  if ($options['mode'] === 'consumer') {
    foreach (['console-ref', 'core-root', 'core-ref', 'ref'] as $required) {
      if (!isset($options[$required])) { throw new RuntimeException('--' . $required . ' is required in consumer mode.'); }
    }
    $console = Assegai\Console\Releases\SourceCheckout::at($options['console-root'] ?? dirname(__DIR__));
    $files = glob(__DIR__ . '/../src/Releases/*.php') ?: [];
    $console->verifyFiles($options['console-ref'], [...$files, __FILE__, __DIR__ . '/../src/Util/ProcessRunner.php', __DIR__ . '/../src/Util/ProcessResult.php',
      __DIR__ . '/policy/composer.json', __DIR__ . '/policy/composer.lock'], dirname(__DIR__));
    $core = Assegai\Console\Releases\SourceCheckout::at($options['core-root']);
    $core->verifyFiles($options['core-ref'], [$options['modules'], $options['inventory']]);
    $sourcePins = ['console' => $options['console-ref'], 'core' => $options['core-ref']];
  }
  if (in_array($options['mode'], ['package', 'consumer'], true)) {
    if (!isset($options['manifest'])) { throw new RuntimeException('--manifest is required in package mode.'); }
    $result = $reader->validateManifestFiles($options['inventory'], $options['modules'], $options['manifest'], $options['ref'] ?? null);
    if ($sourcePins !== null) { $result['sourcePins'] = $sourcePins; }
  } else {
    $result = $reader->validateFiles($options['inventory'], $options['modules'], Assegai\Console\Releases\ReleaseStage::from($options['mode']),
      $options['evidence'] ?? null, $options['website-manifest'] ?? null, $options['website-lock'] ?? null)->toArray();
  }
} catch (Throwable $error) {
  $result = ['schema' => 1, 'status' => 'blocked', 'issues' => [['code' => 'invalid-input', 'path' => 'inputs', 'message' => $error->getMessage()]]];
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
exit(in_array($result['status'], ['passed', 'ready'], true) ? 0 : 1);
