<?php

use Assegai\Console\Util\ProcessRunner;

it('captures both output streams without blocking and preserves the actual exit status', function () {
  $result = (new ProcessRunner())->run([PHP_BINARY, '-d', 'memory_limit=64M', '-r',
    'fwrite(STDOUT, str_repeat("o", 524288)); fwrite(STDERR, str_repeat("e", 524288)); exit(7);',
  ], __DIR__);
  expect($result->status)->toBe(7);
  expect($result->successful)->toBeFalse();
  expect(strlen($result->stdout))->toBe(524288);
  expect(strlen($result->stderr))->toBe(524288);
});

it('terminates a process that exceeds its deadline', function () {
  expect(fn() => (new ProcessRunner())->run([PHP_BINARY, '-d', 'memory_limit=64M', '-r', 'usleep(500000);'], __DIR__, 0))
    ->toThrow(RuntimeException::class, 'time or output limit');
});

it('enforces an explicitly smaller output budget without changing the default capture budget', function () {
  expect(fn() => (new ProcessRunner())->run([PHP_BINARY, '-r', 'echo str_repeat("x", 4096);'], __DIR__, 5, 1024))
    ->toThrow(RuntimeException::class, 'time or output limit');
});
