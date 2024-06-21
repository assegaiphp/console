<?php

namespace Assegai\Console\Core\Migrations\Listers;

use Assegai\Console\Core\Migrations\Listers\AbstractMigrationLister;

/**
 * Class RanMigrationsLister. This class provides a list of ran migrations.
 *
 * @package Assegai\Console\Core\Migrations\Listers
 */
class AllMigrationsLister extends AbstractMigrationLister
{
  /**
   * @inheritDoc
   */
  public function list(): array|false
  {
    if (! file_exists($this->migrator->getMigrationsDirectoryPath() ) )
    {
      $this->output->writeln('<error>The migrations directory does not exist</error>');
      return false;
    }

    return array_values(array_diff(scandir($this->migrator->getMigrationsDirectoryPath()), ['.', '..']) ?? []);
  }
}