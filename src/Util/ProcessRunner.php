<?php

namespace Assegai\Console\Util;

use RuntimeException;

/** Runs argument arrays without a shell, with bounded temporary output. */
final class ProcessRunner
{
  /** @param non-empty-list<string> $command
   * @param array<string, string>|null $environment Null preserves normal environment inheritance.
   */
  public function run(array $command, string $directory, int $timeoutSeconds = 120, int $maxOutputBytes = 8388608, ?array $environment = null): ProcessResult
  {
    if ($timeoutSeconds < 0 || $maxOutputBytes < 1) {
      throw new RuntimeException('The process deadline must be nonnegative and its output limit positive.');
    }
    $stdout = tmpfile();
    $stderr = tmpfile();
    if ($stdout === false || $stderr === false) {
      if (is_resource($stdout)) {
        fclose($stdout);
      }
      if (is_resource($stderr)) {
        fclose($stderr);
      }
      throw new RuntimeException('Cannot create temporary process output.');
    }
    $process = null;
    try {
      $process = proc_open($command, [0 => ['file', 'php://stdin', 'r'], 1 => $stdout, 2 => $stderr], $pipes, $directory, $environment);
      if (!is_resource($process)) {
        throw new RuntimeException('Cannot start the requested process.');
      }
      $deadline = microtime(true) + $timeoutSeconds;
      do {
        $status = proc_get_status($process);
        $outputBytes = (fstat($stdout)['size'] ?? 0) + (fstat($stderr)['size'] ?? 0);
        if ($outputBytes > $maxOutputBytes || microtime(true) > $deadline) {
          throw new RuntimeException('The process exceeded its time or output limit.');
        }
        if ($status['running']) {
          usleep(20000);
        }
      } while ($status['running']);

      $closedStatus = proc_close($process);
      $process = null;
      rewind($stdout);
      rewind($stderr);
      $output = stream_get_contents($stdout);
      $errors = stream_get_contents($stderr);
      return new ProcessResult(
        $status['exitcode'] >= 0 ? $status['exitcode'] : $closedStatus,
        $output === false ? '' : $output,
        $errors === false ? '' : $errors,
      );
    } finally {
      if (is_resource($process)) {
        proc_terminate($process);
        usleep(20000);
        if (proc_get_status($process)['running']) {
          proc_terminate($process, 9);
        }
        proc_close($process);
      }
      fclose($stdout);
      fclose($stderr);
    }
  }
}
