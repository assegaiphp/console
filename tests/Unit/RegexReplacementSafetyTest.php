<?php

describe('regex replacement safety', function () {
  it('does not use PHP-interpolated numeric regex replacement backreferences', function () {
    $roots = [
      __DIR__ . '/../../src',
      __DIR__ . '/../../templates',
    ];
    $violations = [];

    foreach ($roots as $root) {
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
      );

      foreach ($files as $file) {
        if (! $file->isFile()) {
          continue;
        }

        $filename = $file->getPathname();
        $contents = file_get_contents($filename);

        if ($contents === false) {
          continue;
        }

        if (preg_match('/\$\{[0-9]+}/', $contents) === 1) {
          $violations[] = $filename . ' contains ${n} interpolation syntax';
        }

        if (preg_match('/preg_replace\s*\((?:[^;]|\R){0,400}?,\s*"[^"]*\$[0-9]/m', $contents) === 1) {
          $violations[] = $filename . ' contains double-quoted preg_replace numeric backreferences';
        }
      }
    }

    expect($violations)->toBe([]);
  });
});
