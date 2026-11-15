<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

/** Core owns this reviewed data; candidate/check identities live in separate receipts. */
final readonly class ReleaseInventory
{
  public const string CONSOLE_PACKAGE = 'assegaiphp/console';
  public const string CORE_PACKAGE = 'assegaiphp/core';
  /** @param array<string, array{version: string, installConstraint: string}> $packages
   * @param array<string, string> $packageReleaseLines Derived from canonical module policy.
   * @param array<string, ModulePublication> $publication Derived from canonical module lifecycle.
   */
  public function __construct(public string $releaseLine, public array $packages, public WebsiteRequirements $website, public array $packageReleaseLines, public array $publication) {}

  /** @return array<string, array{version: string, installConstraint: string}> */
  public function releasedPackages(): array
  {
    return array_filter($this->packages, fn(array $package, string $name): bool => ($this->publication[$name] ?? null) === ModulePublication::Released, ARRAY_FILTER_USE_BOTH);
  }

  /** @return array<string, string> */
  public function recommendedConstraints(): array
  {
    return array_map(static fn(array $package): string => $package['installConstraint'], $this->releasedPackages());
  }

  /** @return list<string> */
  public function requirementIssues(string $name, string $constraint): array
  {
    $package = $this->releasedPackages()[$name] ?? null;
    $line = $this->packageReleaseLines[$name] ?? null;
    return $package === null || $line === null ? ['The package is not a released module in this inventory.']
      : ReleaseInventoryValidator::constraintIssues($package['version'], $constraint, $line, requirePlannedVersion: false);
  }

  /** @return array{schema: int, inventorySha256: string, canonicalModulesSha256: string, requirements: array<string, mixed>} */
  public function websiteRequest(string $inventorySha256, string $canonicalModulesSha256): array
  {
    return ['schema' => 1, 'inventorySha256' => $inventorySha256, 'canonicalModulesSha256' => $canonicalModulesSha256,
      'requirements' => $this->website->toArray()];
  }
}
