<?php

namespace Assegai\Console\Core\Migrations\Interfaces;

/**
 * Interface MigrationListerInterface. This interface defines the methods that a migration lister class should implement.
 *
 * @package Assegai\Console\Core\Migrations
 */
interface MigrationListerInterface
{
  /**
   * List the migrations.
   *
   * @return array<string>|array<int|string, mixed>|false The list of migrations or false if an error occurred.
   */
  public function list(): array|false;
}