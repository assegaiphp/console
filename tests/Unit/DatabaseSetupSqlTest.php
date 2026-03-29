<?php

use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Database\PostgreSQLDatabase;

describe('database setup SQL generation', function () {
  it('builds identifier-safe MySQL create database SQL', function () {
    $reflection = new ReflectionClass(MySQLDatabase::class);
    $method = $reflection->getMethod('buildCreateDatabaseSql');
    $sql = $method->invoke(null, 'garconio-db');
    $escapedSql = $method->invoke(null, 'garconio`prod');

    expect($sql)->toBe('CREATE DATABASE IF NOT EXISTS `garconio-db`;');
    expect($escapedSql)->toBe('CREATE DATABASE IF NOT EXISTS `garconio``prod`;');
  });

  it('builds identifier-safe PostgreSQL create database SQL', function () {
    $reflection = new ReflectionClass(PostgreSQLDatabase::class);
    $method = $reflection->getMethod('buildCreateDatabaseSql');
    $sql = $method->invoke(null, 'garconio_db');
    $escapedSql = $method->invoke(null, 'garconio"prod');

    expect($sql)->toBe('CREATE DATABASE "garconio_db";');
    expect($escapedSql)->toBe('CREATE DATABASE "garconio""prod";');
  });
});
