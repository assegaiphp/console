<?php

declare(strict_types=1);

namespace Tests\Unit;

use Assegai\Console\Releases\DependencySupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DependencySupportTest extends TestCase
{
  public function testReviewedContractAndExecutedSampleHaveDistinctIdentitiesAndScope(): void
  {
    self::assertSame([], DependencySupport::issues('^0.10.1 || ~0.11.0', '~0.11.0', self::support()));
    self::assertNotSame([], DependencySupport::issues('^0.10.1 || ~0.11.0', null, self::support()));
    self::assertNotSame([], DependencySupport::issues('^0.10.1 || ~0.11.0', '~0.11.0', null));
  }

  #[DataProvider('invalidEvidence')]
  public function testRejectsUncoveredClaimsAndInvalidExecutedSamples(string $path, mixed $value): void
  {
    $support = json_decode(json_encode(self::support(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $target = &$support;
    foreach (explode('.', $path) as $key) { $target = &$target[$key]; }
    $target = $value;
    self::assertNotSame([], DependencySupport::issues('^0.10.1 || ~0.11.0', '~0.11.0',
      json_decode(json_encode($support, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR)));
  }

  public static function support(): \stdClass
  {
    return json_decode(json_encode(['requirement' => '^0.10.1 || ~0.11.0',
      'contracts' => [['interval' => '^0.10.1', 'historySha256' => 'sha256:' . str_repeat('a', 64), 'apiBasisSha256' => 'sha256:' . str_repeat('b', 64)]],
      'samples' => [['version' => '0.10.1', 'sourceSha' => str_repeat('c', 40), 'lockSha256' => 'sha256:' . str_repeat('d', 64),
        'graphSha256' => 'sha256:' . str_repeat('e', 64), 'check' => ['status' => 'passed', 'environment' => 'controlled PHP 8.4 fixture']]],
    ], JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
  }

  /** @return list<array{string, mixed}> */
  public static function invalidEvidence(): array
  {
    return [
      ['requirement', '~0.11.0'], ['contracts.0.interval', '=0.10.1'],
      ['contracts.0.interval', '>=0.10.1 <0.10.2 || >=0.10.3 <0.11.0'], ['contracts.0.interval', '*'],
      ['contracts.0.historySha256', 'unbound'], ['contracts.0.apiBasisSha256', 'unbound'], ['samples', []],
      ['samples.0.version', '0.10.2-beta'], ['samples.0.sourceSha', 'unbound'], ['samples.0.lockSha256', 'unbound'],
      ['samples.0.graphSha256', 'unbound'], ['samples.0.check.status', 'planned'], ['samples.0.check.environment', ''],
    ];
  }
}
