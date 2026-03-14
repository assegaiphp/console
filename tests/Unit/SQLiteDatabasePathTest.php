<?php

use Assegai\Console\Core\Database\SQLiteDatabase;

describe('SQLite database paths', function () {
  it('resolves relative on-disk paths inside the workspace', function () {
    expect(SQLiteDatabase::normalizePath('.data/payroll_console.sq3', '/tmp/assegai-app'))
      ->toBe('/tmp/assegai-app/.data/payroll_console.sq3');
  });

  it('strips legacy sqlite dsn prefixes from stored file paths', function () {
    expect(SQLiteDatabase::normalizePath('sqlite:database.sqlite', '/tmp/assegai-app'))
      ->toBe('/tmp/assegai-app/database.sqlite');
  });

  it('preserves in-memory sqlite targets', function () {
    expect(SQLiteDatabase::normalizePath(':memory:', '/tmp/assegai-app'))
      ->toBe(':memory:');

    expect(SQLiteDatabase::normalizePath('file::memory:?cache=shared', '/tmp/assegai-app'))
      ->toBe('file::memory:?cache=shared');
  });
});
