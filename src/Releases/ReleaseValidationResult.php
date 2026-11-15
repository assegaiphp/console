<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

final readonly class ReleaseValidationResult
{
  public bool $isReady;

  /** @param list<array{code: string, path: string, message: string}> $issues */
  public function __construct(
    public ReleaseStage $stage,
    public ?ReleaseInventory $inventory,
    public ?string $inventorySha256,
    public ?string $canonicalModulesSha256,
    public array $issues,
  ) {
    $this->isReady = $inventory !== null && $issues === [];
  }

  /** @return array<string, mixed> */
  public function toArray(): array
  {
    return ['schema' => 1, 'stage' => $this->stage->value, 'status' => $this->isReady ? 'ready' : 'blocked',
      'inventorySha256' => $this->inventorySha256, 'canonicalModulesSha256' => $this->canonicalModulesSha256,
      'releaseLine' => $this->inventory?->releaseLine, 'website' => $this->inventory?->website->toArray(), 'issues' => $this->issues];
  }
}
