<?php

use Assegai\Console\Core\ProjectTemplateDefaults;

describe('Project template defaults', function () {
  it('includes the current web component defaults for new projects', function () {
    $config = ProjectTemplateDefaults::loadAssegaiConfig();

    expect($config['webComponents']['prefix'])->toBe('app');
    expect($config['webComponents']['hotReload']['enabled'])->toBeTrue();
    expect($config['webComponents']['hotReload']['path'])->toBe('public/.assegai/wc-hot-reload.json');
  });

  it('pins the recommended core dependency for new projects', function () {
    $composer = ProjectTemplateDefaults::loadComposerConfig();

    expect($composer['require'][PACKAGE_NAME_CORE])->toBe(RECOMMENDED_CORE_VERSION_CONSTRAINT);
    expect($composer['autoload']['psr-4'][DEFAULT_NAMESPACE . '\\'])->toBe('src/');
  });
});
