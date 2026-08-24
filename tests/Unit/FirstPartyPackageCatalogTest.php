<?php

use Assegai\Console\Core\Packages\FirstPartyPackageCatalog;

describe('FirstPartyPackageCatalog', function () {
  it('resolves first-party shortcuts without requiring an allowlist entry', function () {
    expect(FirstPartyPackageCatalog::resolve('rabbitmq'))->toBe([
      'packageName' => 'assegaiphp/rabbitmq',
      'constraint' => '*',
    ]);
  });

  it('resolves full first-party Composer package names', function () {
    expect(FirstPartyPackageCatalog::resolve('AssegaiPHP/Beanstalkd'))->toBe([
      'packageName' => 'assegaiphp/beanstalkd',
      'constraint' => '*',
    ]);
  });

  it('keeps release-line constraints for framework-coupled packages', function (string $package, string $packageName, string $constraint) {
    expect(FirstPartyPackageCatalog::resolve($package))->toBe([
      'packageName' => $packageName,
      'constraint' => $constraint,
    ]);
  })->with([
    'core shortcut' => ['core', PACKAGE_NAME_CORE, RECOMMENDED_CORE_VERSION_CONSTRAINT],
    'orm shortcut' => ['orm', PACKAGE_NAME_ORM, RECOMMENDED_ORM_VERSION_CONSTRAINT],
    'events package name' => [PACKAGE_NAME_EVENTS, PACKAGE_NAME_EVENTS, RECOMMENDED_EVENTS_VERSION_CONSTRAINT],
  ]);

  it('rejects packages outside the first-party namespace', function (string $package) {
    expect(FirstPartyPackageCatalog::resolve($package))->toBeNull();
  })->with([
    'another-vendor/rabbitmq',
    'assegaiphp/',
    'assegaiphp/rabbit/mq',
    'rabbit mq',
    '',
  ]);
});
