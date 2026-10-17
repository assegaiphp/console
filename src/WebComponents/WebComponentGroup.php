<?php

namespace Assegai\Console\WebComponents;

use Assegai\Console\Util\Path;

final readonly class WebComponentGroup
{
  /** @param list<string> $sources Absolute source files or directories. */
  public function __construct(
    public string $workspace,
    public ?string $name,
    public array $sources,
    public string $output,
    public string $cacheDirectory,
    public string $hotReloadPath,
  ) {
  }

  public function entryFilename(): string
  {
    return Path::join($this->cacheDirectory, WebComponentConfig::ENTRY_FILENAME);
  }

  public function manifestFilename(): string
  {
    return Path::join($this->cacheDirectory, WebComponentConfig::MANIFEST_FILENAME);
  }

  public function metafileFilename(): ?string
  {
    return $this->name === null ? null : Path::join($this->cacheDirectory, 'esbuild-meta.json');
  }
}
