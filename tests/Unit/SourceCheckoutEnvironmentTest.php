<?php

namespace Tests\Unit;

use Assegai\Console\Util\ProcessRunner;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

final class SourceCheckoutEnvironmentTest extends TestCase
{
  private string $directory;
  /** @var array<string, false> */
  private array $gitEnvironment = [];

  protected function setUp(): void
  {
    $this->directory = sys_get_temp_dir() . '/assegai-source-env-' . bin2hex(random_bytes(8));
    mkdir($this->directory);
    $names = new Process(['git', 'rev-parse', '--local-env-vars'], $this->directory, timeout: 5);
    $names->mustRun();
    // Fixture creation must also be safe when the suite itself runs in a hook.
    foreach (explode("\n", trim($names->getOutput())) as $name) {
      $this->gitEnvironment[trim($name)] = false;
    }
  }

  protected function tearDown(): void
  {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); }
      else { unlink($file->getPathname()); }
    }
    rmdir($this->directory);
  }

  public function testForeignSourceObservationWorksInsideAnActualLinkedWorktreeHook(): void
  {
    $subject = $this->repository('subject', 'subject bytes');
    $foreign = $this->repository('foreign', 'foreign bytes');
    $foreignHead = $this->git($foreign, ['rev-parse', 'HEAD']);
    $linked = $this->directory . '/linked';
    $this->git($subject, ['worktree', 'add', '-b', 'fixture-linked', $linked, 'HEAD']);
    $loader = $this->directory . '/autoload.php';
    $source = var_export(dirname(__DIR__, 2) . '/src/', true);
    file_put_contents($loader, '<?php spl_autoload_register(static function(string $class): void { $prefix="Assegai\\\\Console\\\\"; if(str_starts_with($class,$prefix)){require ' . $source . ' . str_replace("\\\\", "/", substr($class,strlen($prefix))) . ".php";}});');
    $probe = $this->directory . '/observe.php';
    file_put_contents($probe, <<<'PHP'
<?php
require $argv[1];
$gitDir = getenv('GIT_DIR');
$source = Assegai\Console\Releases\SourceCheckout::at($argv[2]);
$source->verifyFiles($argv[3], [$argv[2] . '/nested/proof.txt']);
$child = (new Assegai\Console\Util\ProcessRunner())->run([PHP_BINARY, '-r', 'echo getenv("GIT_DIR");'], getcwd(), timeoutSeconds: 5);
echo json_encode([
  'hookHasGitContext' => is_string($gitDir) && $gitDir !== '',
  'root' => $source->root, 'head' => $source->head(),
  'contents' => $source->fileAt($argv[3], 'nested/proof.txt'), 'clean' => $source->isClean(),
  'parentContextPreserved' => getenv('GIT_DIR') === $gitDir,
  'defaultChildContextPreserved' => $child->successful && $child->stdout === $gitDir,
], JSON_THROW_ON_ERROR);
PHP);
    $hook = $subject . '/.git/hooks/pre-push';
    file_put_contents($hook, "#!/usr/bin/env bash\nexec " . implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, $probe, $loader, $foreign, $foreignHead])) . "\n");
    chmod($hook, 0755);
    $process = new Process(['git', '-C', $linked, 'hook', 'run', '--to-stdin=/dev/stdin', 'pre-push'], $linked, $this->gitEnvironment, timeout: 15);
    $process->setInput('refs/heads/fixture-linked ' . $foreignHead . ' refs/heads/develop ' . str_repeat('0', 40) . "\n");
    $process->run();
    self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    self::assertSame([
      'hookHasGitContext' => true, 'root' => realpath($foreign), 'head' => $foreignHead,
      'contents' => 'foreign bytes', 'clean' => true,
      'parentContextPreserved' => true, 'defaultChildContextPreserved' => true,
    ], json_decode($process->getOutput() . $process->getErrorOutput(), true, flags: JSON_THROW_ON_ERROR));
  }

  public function testProcessEnvironmentRemainsInheritedUnlessExplicitlyProvided(): void
  {
    $previous = getenv('ASSEGAI_PROCESS_ENV_TEST');
    putenv('ASSEGAI_PROCESS_ENV_TEST=inherited');
    try {
      $runner = new ProcessRunner();
      $command = [PHP_BINARY, '-r', 'echo getenv("ASSEGAI_PROCESS_ENV_TEST") ?: "absent";'];
      self::assertSame('inherited', $runner->run($command, $this->directory)->stdout);
      $environment = getenv();
      unset($environment['ASSEGAI_PROCESS_ENV_TEST']);
      self::assertSame('absent', $runner->run($command, $this->directory, environment: $environment)->stdout);
      self::assertSame('inherited', getenv('ASSEGAI_PROCESS_ENV_TEST'));
    } finally {
      putenv($previous === false ? 'ASSEGAI_PROCESS_ENV_TEST' : 'ASSEGAI_PROCESS_ENV_TEST=' . $previous);
    }
  }

  private function repository(string $name, string $contents): string
  {
    $directory = $this->directory . '/' . $name;
    mkdir($directory . '/nested', 0755, true);
    file_put_contents($directory . '/nested/proof.txt', $contents);
    $this->git($directory, ['init', '--initial-branch=fixture']);
    $this->git($directory, ['add', 'nested/proof.txt']);
    $this->git($directory, ['-c', 'user.name=Source Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'test: record controlled source fixture']);
    return $directory;
  }

  /** @param list<string> $arguments */
  private function git(string $directory, array $arguments): string
  {
    $process = new Process(['git', '-C', $directory, ...$arguments], $directory, $this->gitEnvironment, timeout: 5);
    $process->mustRun();
    return trim($process->getOutput());
  }
}
