<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

use Assegai\Console\Util\ProcessRunner;
use RuntimeException;

/** Bounded, read-only observations of explicitly supplied Git source checkouts. */
final readonly class SourceCheckout
{
  /** @param array<string, string> $environment */
  private function __construct(public string $root, private ProcessRunner $runner, private array $environment) {}

  public static function at(string $directory): self
  {
    $root = realpath($directory);
    if ($root === false || !is_dir($root)) { throw new RuntimeException('An explicit source checkout is required.'); }
    $runner = new ProcessRunner();
    // Hooks export the pushing repository's context. Git defines which variables
    // must be cleared before observing another checkout; keep all other settings.
    $variables = $runner->run(['git', 'rev-parse', '--local-env-vars'], $root, timeoutSeconds: 5, maxOutputBytes: 8192);
    if (!$variables->successful || trim($variables->stdout) === '') {
      throw new RuntimeException('Cannot identify Git repository-local environment variables.');
    }
    $environment = getenv();
    foreach (explode("\n", trim($variables->stdout)) as $name) {
      $name = trim($name);
      if (preg_match('/\AGIT_[A-Z0-9_]+\z/', $name) !== 1) {
        throw new RuntimeException('Git reported an invalid repository-local environment variable.');
      }
      unset($environment[$name]);
    }
    $checkout = new self($root, $runner, $environment);
    if (realpath(trim($checkout->git(['rev-parse', '--show-toplevel']))) !== $root) {
      throw new RuntimeException('The source directory must identify its actual Git checkout root.');
    }
    return $checkout;
  }

  /** @phpstan-impure */
  public function head(): string { return trim($this->git(['rev-parse', 'HEAD'])); }

  /** @phpstan-impure */
  public function isClean(): bool { return trim($this->git(['status', '--porcelain', '--untracked-files=normal'])) === ''; }

  public function fileAt(string $ref, string $relativePath): string
  {
    if (preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/', $ref) !== 1 || $relativePath === ''
      || str_starts_with($relativePath, '/') || str_contains($relativePath, '\\')
      || preg_match('~(?:\A|/)\.\.?(?:/|\z)|[\x00-\x1f\x7f]~', $relativePath)) {
      throw new RuntimeException('Source pins require a complete Git identity and a contained relative file path.');
    }
    return $this->git(['show', $ref . ':' . $relativePath]);
  }

  /** @param list<string> $filenames */
  public function verifyFiles(string $ref, array $filenames, ?string $copyRoot = null): void
  {
    $copyRoot = realpath($copyRoot ?? $this->root);
    if ($copyRoot === false) { throw new RuntimeException('An explicit source copy root is required.'); }
    $prefix = rtrim($copyRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    foreach ($filenames as $filename) {
      $path = realpath($filename);
      if ($path === false || !is_file($path) || !str_starts_with($path, $prefix)) {
        throw new RuntimeException('Pinned input files must belong to the explicit source checkout.');
      }
      $contents = @file_get_contents($path, length: 1048577);
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($prefix)));
      if ($contents === false || strlen($contents) > 1048576 || $contents !== $this->fileAt($ref, $relative)) {
        throw new RuntimeException('An input differs from its explicitly pinned source commit.');
      }
    }
  }

  /** @param list<string> $arguments
   * @phpstan-impure
   */
  private function git(array $arguments): string
  {
    $result = $this->runner->run(['git', '--no-optional-locks', '-C', $this->root, ...$arguments], $this->root,
      timeoutSeconds: 5, maxOutputBytes: 1048576, environment: $this->environment);
    if (!$result->successful) { throw new RuntimeException('Cannot verify the explicitly supplied Git source.'); }
    return $result->stdout;
  }
}
