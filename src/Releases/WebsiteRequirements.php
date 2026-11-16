<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

final readonly class WebsiteRequirements
{
  /** @param array<string, string> $archives */
  public function __construct(
    public string $documentationVersion,
    public string $previousDocumentationVersion,
    public string $articlePath,
    public string $upgradeFrom,
    public string $upgradeTo,
    public array $archives,
  ) {}

  /** @return array{documentationVersion: string, previousDocumentationVersion: string, articlePath: string, upgradeFrom: string, upgradeTo: string, archives: array<string, string>} */
  public function toArray(): array
  {
    return ['documentationVersion' => $this->documentationVersion, 'previousDocumentationVersion' => $this->previousDocumentationVersion,
      'articlePath' => $this->articlePath, 'upgradeFrom' => $this->upgradeFrom, 'upgradeTo' => $this->upgradeTo, 'archives' => $this->archives];
  }
}
