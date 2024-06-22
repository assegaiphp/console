<?php

namespace Assegai\Console\Core\Migrations\Listers;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Migrations\Interfaces\MigrationListerInterface;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractMigrationLister. This class provides a base implementation for the migration lister interface.
 *
 * @package Assegai\Console\Core\Migrations\Listers
 */
abstract class AbstractMigrationLister implements MigrationListerInterface
{
  /**
   * AbstractMigrationLister constructor.
   *
   * @param MigratorInterface $migrator The migrator.
   * @param InputInterface|null $input The input.
   * @param OutputInterface|null $output The output.
   */
  public function __construct(
    protected MigratorInterface $migrator,
    protected ?InputInterface $input = null,
    protected ?OutputInterface $output = null,
  )
  {
    if (!$this->input) {
      $this->input = new ArgvInput();
    }

    if (!$this->output) {
      $this->output = new ConsoleOutput();
    }
  }
}