<?php

namespace Assegai\Console\Core\Database\Interfaces;

/**
 * Interface MigrationInterface
 * @package Assegai\Console\Core\Database\Interfaces
 */
interface MigrationInterface
{
  /**
   * Run the migration.
   *
   * @param DatabaseConnectionInterface $connection The database connection.
   */
  public function up(DatabaseConnectionInterface $connection): void;

  /**
   * Rollback the migration.
   *
   * @param DatabaseConnectionInterface $connection The database connection.
   */
  public function down(DatabaseConnectionInterface $connection): void;
}