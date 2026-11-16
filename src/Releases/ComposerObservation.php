<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

use RuntimeException;
use stdClass;

/** Reads actual Composer bytes; dependency range policy belongs to the release validator. */
final readonly class ComposerObservation
{
  /** @param array<string, string> $versions
   * @param array<string, mixed> $require
   * @param array<string, mixed> $requireDev
   */
  private function __construct(public string $name, public string $sourceSha, public string $lockSha256, public array $versions, public array $require, public array $requireDev) {}

  public static function fromFiles(string $manifestPath, string $lockPath): self
  {
    $reader = new ReleaseInventoryValidator();
    [$manifest, $manifestHash] = $reader->read($manifestPath);
    [$lock, $lockHash] = $reader->read($lockPath);
    $manifestPath = realpath($manifestPath);
    $lockPath = realpath($lockPath);
    if ($manifestPath === false || $lockPath === false || basename($manifestPath) !== 'composer.json'
      || basename($lockPath) !== 'composer.lock' || dirname($manifestPath) !== dirname($lockPath)) {
      throw new RuntimeException('Website Composer files must identify the actual composer.json and composer.lock in one source checkout.');
    }
    $root = dirname($manifestPath);
    $checkout = SourceCheckout::at($root);
    if (!$checkout->isClean()) {
      throw new RuntimeException('Website Composer publication evidence requires a clean source checkout.');
    }
    $source = $checkout->head();
    foreach (['composer.json' => $manifestHash, 'composer.lock' => $lockHash] as $filename => $hash) {
      if ('sha256:' . hash('sha256', $checkout->fileAt($source, $filename)) !== $hash) {
        throw new RuntimeException('Observed Website Composer bytes differ from their recorded source commit.');
      }
    }
    if (!is_string($manifest['name'] ?? null)) {
      throw new RuntimeException('The observed Composer manifest must identify its owning package.');
    }
    $versions = [];
    foreach (['packages', 'packages-dev'] as $section) {
      $entries = $lock[$section] ?? null;
      if (!is_array($entries) || !array_is_list($entries)) {
        throw new RuntimeException('The actual Composer lock must contain package lists.');
      }
      foreach ($entries as $value) {
        $entry = ReleaseInventoryValidator::map($value);
        if (!is_string($entry['name'] ?? null) || !is_string($entry['version'] ?? null)) {
          throw new RuntimeException('Locked packages require names and exact versions.');
        }
        if (str_starts_with($entry['name'], 'assegaiphp/')) {
          if (isset($versions[$entry['name']])) {
            throw new RuntimeException('The actual Composer lock contains a duplicate first-party package.');
          }
          $versions[$entry['name']] = $entry['version'];
        }
      }
    }
    if ($checkout->head() !== $source || !$checkout->isClean()) {
      throw new RuntimeException('Website source changed during Composer observation.');
    }
    return new self($manifest['name'], $source, $lockHash, $versions,
      ReleaseInventoryValidator::map($manifest['require'] ?? new stdClass()),
      ReleaseInventoryValidator::map($manifest['require-dev'] ?? new stdClass()));
  }
}
