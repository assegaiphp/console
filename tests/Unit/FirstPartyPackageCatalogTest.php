<?php

use Assegai\Console\Core\Packages\FirstPartyPackageCatalog;

describe('FirstPartyPackageCatalog', function () {
  it('preserves later patches on the reviewed line and rejects unresolvable or different lines', function () {
    $inventory = Assegai\Console\Releases\BundledRelease::inventory();
    expect($inventory->requirementIssues('assegaiphp/core', '~0.11.9'))->toBe([])
      ->and($inventory->requirementIssues('assegaiphp/core', '^0.12'))->not->toBe([])
      ->and($inventory->requirementIssues('assegaiphp/core', '>0.12 <0.12'))->not->toBe([])
      ->and($inventory->requirementIssues('assegaiphp/core', 'dev-main'))->not->toBe([]);
  });
  it('uses every released canonical module and its reviewed minimum', function () {
    $constraints = FirstPartyPackageCatalog::recommendedConstraints();
    expect($constraints)->toHaveCount(14)
      ->toMatchArray([
        'assegaiphp/core' => '~0.11.1', 'assegaiphp/console' => '~0.11.1',
        'assegaiphp/events' => '~0.11.0', 'assegaiphp/orm' => '~0.11.0',
        'assegaiphp/attributes' => '~1.0.3', 'assegaiphp/rabbitmq' => '~1.2.0', 'assegaiphp/beanstalkd' => '~1.2.0',
      ])->not->toHaveKey('assegaiphp/cms');
    foreach ($constraints as $package => $constraint) {
      expect(FirstPartyPackageCatalog::resolve($package))->toBe(['packageName' => $package, 'constraint' => $constraint]);
    }
  });

  it('resolves public queue shortcuts through the coordinated inventory', function () {
    expect(FirstPartyPackageCatalog::resolve('rabbitmq'))->toBe([
      'packageName' => 'assegaiphp/rabbitmq',
      'constraint' => '~1.2.0',
    ]);
  });

  it('resolves full first-party Composer package names', function () {
    expect(FirstPartyPackageCatalog::resolve('AssegaiPHP/Beanstalkd'))->toBe([
      'packageName' => 'assegaiphp/beanstalkd',
      'constraint' => '~1.2.0',
    ]);
  });

  it('uses the supported constraint for each managed package', function (string $package, string $packageName, string $constraint) {
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
    'assegaiphp/cms',
    'assegaiphp/cli',
    'assegaiphp/unknown',
    'another-vendor/rabbitmq',
    'assegaiphp/',
    'assegaiphp/rabbit/mq',
    'rabbit mq',
    '',
  ]);
});
