<?php

namespace Assegai\Console\Core\Migrations\Listers;

use Assegai\Console\Core\Migrations\Listers\AbstractMigrationLister;

/**
 * Class PendingMigrationsLister. This class provides a list of pending migrations.
 *
 * @package Assegai\Console\Core\Migrations\Listers
 */
class PendingMigrationsLister extends AbstractMigrationLister
{
  /**
   * @inheritDoc
   */
  public function list(): array|false
  {
    $allMigrations = $this->migrator->listAll();
    $ranMigrations = $this->migrator->listRan();

    if (false === $allMigrations || false === $ranMigrations)
    {
      $this->output->writeln("<error>Failed to list the migrations</error>\n");
      return false;
    }

    return array_filter($allMigrations, function($migration) use ($ranMigrations) {
      foreach ($ranMigrations as $ranMigration)
      {
        if ($ranMigration['migration'] === $migration)
        {
          return false;
        }
      }

      return true;
    });
  }
}