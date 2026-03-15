<?php

namespace Assegai\Console\WebComponents\Builder;

use Symfony\Component\Console\Command\Command;

final class EsbuildRunner
{
  public function __construct(private ?string $binary = null)
  {
  }

  public function isAvailable(): bool
  {
    return $this->resolveBinary() !== null;
  }

  public function build(string $entryFilename, string $outputFilename, bool $watch = false): int
  {
    $binary = $this->resolveBinary();

    if ($binary === null) {
      return Command::FAILURE;
    }

    $command = sprintf(
      '%s %s --bundle --minify --format=esm --outfile=%s%s',
      $binary,
      escapeshellarg($entryFilename),
      escapeshellarg($outputFilename),
      $watch ? ' --watch' : ''
    );

    passthru($command, $statusCode);

    return $statusCode;
  }

  private function resolveBinary(): ?string
  {
    if ($this->binary !== null && trim($this->binary) !== '') {
      return $this->binary;
    }

    if (is_installed('esbuild')) {
      return 'esbuild';
    }

    if (is_installed('npx')) {
      return 'npx esbuild';
    }

    return null;
  }
}
