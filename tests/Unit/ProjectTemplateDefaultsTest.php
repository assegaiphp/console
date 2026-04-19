<?php

use Assegai\Console\Core\ProjectTemplateDefaults;

describe('Project template defaults', function () {
  it('includes the current assegai.json defaults for new projects', function () {
    $config = ProjectTemplateDefaults::loadAssegaiConfig();

    expect($config['development']['server']['runtime'])->toBe('php');
    expect($config['development']['server']['host'])->toBe(DEFAULT_DEV_SERVER_HOST);
    expect($config['development']['server']['port'])->toBe(DEFAULT_DEV_SERVER_PORT);
    expect($config['development']['server']['openBrowser'])->toBeFalse();
    expect($config['development']['server']['openswoole']['workerNum'])->toBe(1);
    expect($config['development']['server']['openswoole']['taskWorkerNum'])->toBe(0);
    expect($config['development']['server']['openswoole']['maxRequest'])->toBe(0);
    expect($config['development']['server']['openswoole']['enableCoroutine'])->toBeTrue();
    expect($config['development']['server']['openswoole']['hookFlags'])->toBe('all');
    expect($config['cli']['schematics']['paths'])->toBe(['schematics']);
    expect($config['cli']['schematics']['discoverPackages'])->toBeTrue();
    expect($config['cli']['schematics']['allowOverrides'])->toBeFalse();
    expect($config['apiDocs']['enabled'])->toBeTrue();
    expect($config['apiDocs']['exportOnServe'])->toBeFalse();
    expect($config['apiDocs']['exportPath'])->toBe('generated/openapi.json');
    expect($config['webComponents']['enabled'])->toBeTrue();
    expect($config['webComponents']['prefix'])->toBe('app');
    expect($config['webComponents']['bundleUrl'])->toBeNull();
    expect($config['webComponents']['bundlePath'])->toBeNull();
    expect($config['webComponents']['output'])->toBe('public/js/assegai-components.min.js');
    expect($config['webComponents']['buildOnDumpAutoload'])->toBeFalse();
    expect($config['webComponents']['hotReload']['enabled'])->toBeTrue();
    expect($config['webComponents']['hotReload']['path'])->toBe('public/.assegai/wc-hot-reload.json');
    expect($config['webComponents']['hotReload']['pollInterval'])->toBe(1000);
    expect($config['webComponents']['hotReload']['ttl'])->toBe(43200);
  });

  it('pins the recommended core dependency for new projects', function () {
    $composer = ProjectTemplateDefaults::loadComposerConfig();

    expect($composer['require'][PACKAGE_NAME_CORE])->toBe(RECOMMENDED_CORE_VERSION_CONSTRAINT);
    expect($composer['require']['php'])->toBe('^' . MIN_PHP_VERSION);
    expect($composer['autoload']['psr-4'])->toBe([]);
  });

  it('keeps the recommended orm dependency on the same release line as core', function () {
    expect(RECOMMENDED_ORM_VERSION_CONSTRAINT)->toBe(RECOMMENDED_CORE_VERSION_CONSTRAINT);
  });

  it('derives framework release lines from semantic CLI versions', function () {
    expect(recommended_framework_release_line_for_version('0.8.2'))->toBe('^0.8.0');
    expect(recommended_framework_release_line_for_version('v0.9.4'))->toBe('^0.9.0');
    expect(recommended_framework_release_line_for_version('0.10.x-dev'))->toBe('^0.10.0');
    expect(recommended_framework_release_line_for_version('1.1.3'))->toBe('^1.1.0');
  });
});
