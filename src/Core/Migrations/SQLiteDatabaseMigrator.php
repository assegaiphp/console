<?php

namespace Assegai\Console\Core\Migrations;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Core\Migrations\Enumerations\MigrationListerType;
use Assegai\Console\Core\Migrations\Interfaces\MigrationListerInterface;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Assegai\Console\Core\Migrations\Listers\AllMigrationsLister;
use Assegai\Console\Core\Migrations\Listers\PendingMigrationsLister;
use Assegai\Console\Core\Migrations\Listers\RanMigrationsLister;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SQLiteDatabaseMigrator. This class is a migrator for SQLite databases.
 *
 * @package Assegai\Console\Core\Migrations
 */
class SQLiteDatabaseMigrator extends SQLiteDatabase implements MigratorInterface
{

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function up(?int $runs = null): int|false
  {
    $successfulRuns = 0;

    $pendingMigrations = $this->listPending();
    $totalPendingMigrations = count($pendingMigrations);
    $totalMigrationsToRun = min($runs ?: $totalPendingMigrations, $totalPendingMigrations);

    $totalRowsAffected = 0;

    # Foreach migration in the pending migrations
    foreach ($pendingMigrations as $index => $migration)
    {
      # Get the up.sql file content
      $upFilePath = Path::join($this->getMigrationsDirectoryPath(), $migration, 'up.sql');

      if (! file_exists($upFilePath) )
      {
        $this->output->writeln("<error>The up.sql file for migration $migration does not exist</error>\n");
        return false;
      }
      $upFileContent = file_get_contents($upFilePath);

      # Execute the up.sql file
      $statement = $this->query($upFileContent);

      if (false === $statement)
      {
        $this->output->writeln("<error>Failed to execute the up.sql file for migration $migration</error>\n");
        return false;
      }

      $totalRowsAffected += $statement->rowCount();

      # Update the migrations table
      $migrationsTableName = $this->getMigrationsTableName();
      $timestamp = date(DATE_ATOM);
      $sql = "INSERT INTO $migrationsTableName (migration, ran_at) VALUES ('$migration', '$timestamp')";

      $statement = $this->query($sql);

      if (false === $statement)
      {
        $this->output->writeln("<error>Failed to update the migrations table for migration $migration</error>\n");
        return false;
      }

      $successfulRuns++;

      if ($index === $totalMigrationsToRun - 1)
      {
        break;
      }
    }

    $this->output->writeln([
      "<info>RUN</info> $successfulRuns migrations",
      "<info>$totalRowsAffected rows affected</info>\n"
    ], OutputInterface::VERBOSITY_VERBOSE);
    return $successfulRuns;
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function down(?int $rollbacks = null): int|false
  {
    $successfulRollbacks = 0;
    $pendingMigrations = $this->listRan();
    $totalRanMigrations = count($pendingMigrations);
    $totalMigrationsToRollback = min($rollbacks ?: $totalRanMigrations, $totalRanMigrations);

    $totalRowsAffected = 0;

    # Foreach migration in the pending migrations
    foreach ($pendingMigrations as $index => $migration)
    {
      # Get the down.sql file content
      $downFilePath = Path::join($this->getMigrationsDirectoryPath(), $migration, 'down.sql');

      if (! file_exists($downFilePath) )
      {
        $this->output->writeln("<error>The down.sql file for migration $migration does not exist</error>\n");
        return false;
      }
      $downFileContent = file_get_contents($downFilePath);

      # Execute the down.sql file
      $statement = $this->query($downFileContent);

      if (false === $statement)
      {
        $this->output->writeln("<error>Failed to execute the down.sql file for migration $migration</error>\n");
        return false;
      }

      $totalRowsAffected += $statement->rowCount();

      # Update the migrations table
      $migrationsTableName = $this->getMigrationsTableName();
      $sql = "DELETE FROM $migrationsTableName WHERE migration='$migration'";

      $statement = $this->query($sql);

      if (false === $statement)
      {
        $this->output->writeln("<error>Failed to update the migrations table for migration $migration</error>\n");
        return false;
      }

      $successfulRollbacks++;

      if ($index === $totalMigrationsToRollback - 1)
      {
        break;
      }
    }

    $this->output->writeln([
      "<info>ROLLBACK</info> $successfulRollbacks migrations",
      "<info>$totalRowsAffected rows affected</info>\n"
    ], OutputInterface::VERBOSITY_VERBOSE);
    return $successfulRollbacks;
  }

  /**
   * @inheritDoc
   */
  public function reset(): int|false
  {
    // TODO: Implement reset() method.

    return 0;
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function create(string $name): string|false
  {
    $directoryName = date('YmdHis') . '_' . $name;
    $path = Path::join($this->getMigrationsDirectoryPath(), DatabaseType::SQLITE->value, $this->name, $directoryName);

    if (! file_exists($path) )
    {
      if (false === mkdir($path) )
      {
        $this->output->writeln('<error>Failed to create the migration directory</error>');
        return false;
      }
    }

    # Create the up.sql file
    $upMigrationFile = Path::join($path, 'up.sql');
    $upBytes = file_put_contents($upMigrationFile, '');

    if (false === $upBytes)
    {
      $this->output->writeln('<error>Failed to create the migration files</error>');
      return false;
    }

    $relativeUpMigrationFile = str_replace($this->getMigrationsDirectoryPath(), '', $upMigrationFile);
    $this->output->writeln("<info>CREATE</info> $relativeUpMigrationFile");

    # Create the down.sql file
    $downMigrationFile = Path::join($path, 'down.sql');
    $downBytes = file_put_contents($downMigrationFile, '');

    if (false === $downBytes)
    {
      $this->output->writeln('<error>Failed to create the migration files</error>');
      return false;
    }

    $relativeDownMigrationFile = str_replace($this->getMigrationsDirectoryPath(), '', $downMigrationFile);
    $this->output->writeln("<info>CREATE</info> $relativeDownMigrationFile");

    return $path;
  }

  /**
   * @inheritDoc
   */
  public function listAll(): array|false
  {
    return $this->getLister(MigrationListerType::ALL)->list();
  }

  /**
   * @inheritDoc
   */
  public function listRan(): array|false
  {
    return $this->getLister(MigrationListerType::RAN)->list();
  }

  /**
   * @inheritDoc
   */
  public function listPending(): array|false
  {
    return $this->getLister(MigrationListerType::PENDING)->list();
  }

  /**
   * @inheritDoc
   */
  public function last(): string|false
  {
    $query = "SELECT migration FROM {$this->getMigrationsTableName()} ORDER BY ran_at DESC LIMIT 1";
    $statement = $this->query($query);

    if (false === $statement)
    {
      $this->output->writeln('<error>Failed to get the last migration</error>\n');
      return false;
    }

    return $statement->fetchColumn() ?? '';
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function next(): string|false
  {
    $lastMigration = $this->last();

    $allMigrations = $this->listAll();

    $lastMigrationIndex = array_search($lastMigration, $allMigrations);

    if (false === $lastMigrationIndex)
    {
      $this->output->writeln('<error>Failed to get the next migration</error>\n');
      return false;
    }

    $nextMigrationIndex = $lastMigrationIndex + 1;

    return $allMigrations[$nextMigrationIndex] ?? '';
  }

  /**
   * @inheritDoc
   */
  public function getMigrationsDirectoryPath(): string
  {
    return Path::join(getcwd() ?: '', 'migrations', DatabaseType::SQLITE->value, $this->name);
  }

  /**
   * @inheritDoc
   */
  public function getLister(MigrationListerType $type): MigrationListerInterface
  {
    return match($type) {
      MigrationListerType::ALL => new AllMigrationsLister($this),
      MigrationListerType::PENDING => new PendingMigrationsLister($this),
      MigrationListerType::RAN => new RanMigrationsLister($this),
    };
  }
}