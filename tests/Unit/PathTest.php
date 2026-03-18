<?php

use Assegai\Console\Util\Path;

describe('Path', function () {
  it('preserves the current-directory shorthand when normalizing relative paths', function () {
    expect(Path::normalize('.'))->toBe('.');
    expect(Path::normalize('./'))->toBe('.');
    expect(Path::normalize('foo/..'))->toBe('.');
  });
});
