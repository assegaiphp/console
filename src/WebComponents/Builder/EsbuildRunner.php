<?php

namespace Assegai\Console\WebComponents\Builder;

use Symfony\Component\Console\Command\Command;

final class EsbuildRunner
{
  private const int WATCH_TICK_MICROSECONDS = 250000;

  public function __construct(private ?string $binary = null)
  {
  }

  public function isAvailable(): bool
  {
    return $this->resolveBinary() !== null;
  }

  public function build(string $entryFilename, string $outputFilename, bool $watch = false, ?callable $onWatchTick = null): int
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

    if ($watch) {
      return $this->runWatchProcess($command, $onWatchTick);
    }

    passthru($command, $statusCode);

    return $statusCode;
  }

  private function runWatchProcess(string $command, ?callable $onWatchTick = null): int
  {
    $descriptors = [
      0 => ['file', 'php://stdin', 'r'],
      1 => ['file', 'php://stdout', 'w'],
      2 => ['file', 'php://stderr', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
      return Command::FAILURE;
    }

    $exitCode = Command::SUCCESS;

    try {
      while (true) {
        $status = proc_get_status($process);
        if ($onWatchTick !== null) {
          $onWatchTick();
        }

        if (!$status['running']) {
          $exitCode = is_int($status['exitcode']) && $status['exitcode'] >= 0
            ? $status['exitcode']
            : Command::FAILURE;
          break;
        }

        usleep(self::WATCH_TICK_MICROSECONDS);
      }
    } finally {
      proc_close($process);
    }

    return $exitCode;
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
