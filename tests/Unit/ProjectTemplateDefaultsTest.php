<?php

use Assegai\Console\Core\ProjectTemplateDefaults;

describe('Project template defaults', function () {
  it('includes the current assegai.json defaults for new projects', function () {
    $config = ProjectTemplateDefaults::loadAssegaiConfig();

    expect($config['development']['server']['host'])->toBe(DEFAULT_DEV_SERVER_HOST);
    expect($config['development']['server']['port'])->toBe(DEFAULT_DEV_SERVER_PORT);
    expect($config['development']['server']['openBrowser'])->toBeFalse();
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
    expect($composer['autoload']['psr-4'][DEFAULT_NAMESPACE . '\\'])->toBe('src/');
  });
});
