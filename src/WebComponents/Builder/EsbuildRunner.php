<?php

namespace Assegai\Console\WebComponents\Builder;

use Symfony\Component\Console\Command\Command;

final class EsbuildRunner
{
  public const int WATCH_TICK_MICROSECONDS = 250000;

  public function __construct(private ?string $binary = null)
  {
  }

  public function isAvailable(): bool
  {
    return $this->resolveBinary() !== null;
  }

  public function build(string $entryFilename, string $outputFilename, bool $watch = false, ?callable $onWatchTick = null,
    ?string $metafile = null, ?string $workingDirectory = null): int
  {
    if ($watch) {
      return $this->watchMany([['entry' => $entryFilename, 'output' => $outputFilename, 'metafile' => $metafile]],
        $onWatchTick, $workingDirectory);
    }
    $command = $this->command($entryFilename, $outputFilename, false, $metafile);
    if ($command === null) {
      return Command::FAILURE;
    }
    $previous = getcwd();
    try {
      if ($workingDirectory !== null && !chdir($workingDirectory)) {
        return Command::FAILURE;
      }
      passthru($command, $statusCode);
      return $statusCode;
    } finally {
      if ($workingDirectory !== null && $previous !== false) {
        chdir($previous);
      }
    }
  }

  /** @param list<array{entry: string, output: string, metafile: ?string}> $jobs */
  public function watchMany(array $jobs, ?callable $onWatchTick = null, ?string $workingDirectory = null): int
  {
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['file', 'php://stdout', 'w'],
      2 => ['pipe', 'w'],
    ];

    $processes = [];
    $inputs = [];
    $errors = [];
    $buffers = [];
    $failed = [];
    $exitCode = Command::SUCCESS;
    $signal = 0;
    $handlers = [];
    $async = false;
    if (function_exists('pcntl_async_signals')) {
      $async = pcntl_async_signals(true);
      foreach ([SIGINT, SIGTERM] as $number) {
        $handler = pcntl_signal_get_handler($number);
        if (is_int($handler) || is_callable($handler)) {
          $handlers[$number] = $handler;
        }
        pcntl_signal($number, static function (int $number) use (&$signal): void { $signal = $number; });
      }
    }
    try {
      foreach ($jobs as $job) {
        $command = $this->command($job['entry'], $job['output'], true, $job['metafile']);
        if ($command === null) {
          return Command::FAILURE;
        }
        $process = proc_open((PHP_OS_FAMILY === 'Windows' ? '' : 'exec ') . $command,
          $descriptors, $pipes, $workingDirectory);
        if (!is_resource($process)) {
          return Command::FAILURE;
        }
        $processes[] = $process;
        $inputs[] = $pipes[0];
        stream_set_blocking($pipes[2], false);
        $errors[] = $pipes[2];
        $buffers[] = '';
        $failed[] = false;
      }
      while (true) {
        if ($signal !== 0) {
          return 128 + $signal;
        }
        $completed = [];
        foreach ($errors as $index => $error) {
          $chunk = stream_get_contents($error, 65536);
          if ($chunk !== false && $chunk !== '') {
            fwrite(STDERR, $chunk);
            $buffers[$index] .= $chunk;
            while (($end = strpos($buffers[$index], "\n")) !== false) {
              $line = substr($buffers[$index], 0, $end);
              $buffers[$index] = substr($buffers[$index], $end + 1);
              if (str_contains($line, '[ERROR]')) {
                $failed[$index] = true;
              }
              if (str_contains($line, '[watch] build finished')) {
                if (!$failed[$index]) {
                  $completed[] = $index;
                }
                $failed[$index] = false;
              }
            }
            $buffers[$index] = substr($buffers[$index], -65536);
          }
        }
        if ($onWatchTick !== null && $onWatchTick($completed) === false) {
          break;
        }
        foreach ($processes as $process) {
          $status = proc_get_status($process);
          if (!$status['running']) {
            $exitCode = is_int($status['exitcode']) && $status['exitcode'] >= 0
              ? $status['exitcode']
              : Command::FAILURE;
            break 2;
          }
        }
        usleep(self::WATCH_TICK_MICROSECONDS);
      }
    } finally {
      // esbuild watch exits on stdin EOF, including through npx. Close every
      // input first so coordinated shutdown does not wait one second per group.
      foreach ($inputs as $input) {
        fclose($input);
      }
      $deadline = microtime(true) + 1;
      foreach ($processes as $process) {
        while ($this->isRunning($process) && microtime(true) < $deadline) {
          usleep(10000);
        }
        if ($this->isRunning($process)) {
          proc_terminate($process);
          usleep(10000);
          if ($this->isRunning($process)) {
            proc_terminate($process, 9);
          }
        }
        proc_close($process);
      }
      foreach ($errors as $error) {
        if (is_resource($error)) {
          fclose($error);
        }
      }
      foreach ($handlers as $number => $handler) {
        pcntl_signal($number, $handler);
      }
      if ($handlers !== []) {
        pcntl_async_signals($async);
      }
    }

    return $exitCode;
  }

  private function command(string $entry, string $output, bool $watch, ?string $metafile): ?string
  {
    $binary = $this->resolveBinary();
    return $binary === null ? null : sprintf('%s %s --bundle --minify --format=esm --outfile=%s%s%s',
      $binary, escapeshellarg($entry), escapeshellarg($output), $watch ? ' --watch' : '',
      $metafile === null ? '' : ' --metafile=' . escapeshellarg($metafile));
  }

  /**
   * @param resource $process
   * @phpstan-impure
   */
  private function isRunning($process): bool
  {
    return proc_get_status($process)['running'];
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
