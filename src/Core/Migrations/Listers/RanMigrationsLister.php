<?php

namespace Assegai\Console\Core\Migrations\Listers;

use PDO;

/**
 * Class RanMigrationsLister. This class provides a list of ran migrations.
 *
 * @package Assegai\Console\Core\Migrations\Listers
 */
class RanMigrationsLister extends AbstractMigrationLister
{
  /**
   * @inheritDoc
   */
  public function list(): array|false
  {
    $migrationsTableName = $this->migrator->getMigrationsTableName();
    $sql = "SELECT migration, ran_at as ranAt FROM $migrationsTableName ORDER BY migration DESC";

    $statement = $this->migrator->query($sql);

    if (false === $statement)
    {
      $this->output->writeln('<error>Failed to list the migrations that have been run</error>');
      return false;
    }

    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}