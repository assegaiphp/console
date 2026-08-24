<?php

use Assegai\Console\Util\CliInputNormalizer;

describe('CliInputNormalizer', function () {
  it('normalizes the long global update form', function () {
    expect(CliInputNormalizer::normalize(['assegai', 'global', 'update', '--dry-run']))
      ->toBe(['assegai', 'global:update', '--dry-run']);
  });

  it('normalizes the short global update form', function () {
    expect(CliInputNormalizer::normalize(['assegai', '-g', 'update']))
      ->toBe(['assegai', 'global:update']);
  });

  it('normalizes the explicit global option form', function () {
    expect(CliInputNormalizer::normalize(['assegai', '--global', 'update']))
      ->toBe(['assegai', 'global:update']);
  });

  it('does not reinterpret command-specific global flags', function () {
    expect(CliInputNormalizer::normalize(['assegai', 'new', 'demo', '-g']))
      ->toBe(['assegai', 'new', 'demo', '-g']);
  });
});
