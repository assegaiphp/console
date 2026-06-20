<?php

use Assegai\Console\Util\Path;

describe('Path', function () {
  it('preserves the current-directory shorthand when normalizing relative paths', function () {
    expect(Path::normalize('.'))->toBe('.');
    expect(Path::normalize('./'))->toBe('.');
    expect(Path::normalize('foo/..'))->toBe('.');
  });

  it('preserves absolute roots while normalizing supported path styles', function () {
    expect(Path::normalize('/var/www/../app'))->toBe('/var/app');
    expect(Path::normalize('C:\app\..\project'))->toBe('C:/project');
    expect(Path::normalize('C:\..'))->toBe('C:/');
    expect(Path::normalize('C:\app\..'))->toBe('C:/');
    expect(Path::normalize(chr(92) . chr(92) . 'server\share\app'))->toBe('//server/share/app');
  });

  it('detects absolute Unix, Windows drive, and UNC paths', function () {
    expect(Path::isAbsolute('/var/app'))->toBeTrue();
    expect(Path::isAbsolute('C:/app'))->toBeTrue();
    expect(Path::isAbsolute('C:\app'))->toBeTrue();
    expect(Path::isAbsolute(chr(92) . chr(92) . 'server\share'))->toBeTrue();
    expect(Path::isAbsolute('relative/path'))->toBeFalse();
    expect(Path::isAbsolute('C:relative'))->toBeFalse();
    expect(Path::isAbsolute(''))->toBeFalse();
  });
});
