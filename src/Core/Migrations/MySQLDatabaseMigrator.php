<?php /** @noinspection PhpSuperClassIncompatibleWithInterfaceInspection */

namespace Assegai\Console\Core\Migrations;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\MySQLDatabase;
use Assegai\Console\Core\Migrations\Enumerations\MigrationListerType;
use Assegai\Console\Core\Migrations\Interfaces\MigrationListerInterface;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Assegai\Console\Core\Migrations\Listers\AllMigrationsLister;
use Assegai\Console\Core\Migrations\Listers\PendingMigrationsLister;
use Assegai\Console\Core\Migrations\Listers\RanMigrationsLister;
use Assegai\Console\Util\Path;
use PDO;
use PDOException;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MySQLDatabaseMigrator. This class is a migrator for MySQL databases.
 *
 * @package Assegai\Console\Core\Migrations
 */
class MySQLDatabaseMigrator extends MySQLDatabase implements MigratorInterface
{
  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function up(?int $runs = null): int|false
  {
    $successfulRuns = 0;

    $pendingMigrations = $this->listPending();

    if (empty($pendingMigrations)) {
      $this->output->writeln("No pending migrations");
      return 0;
    }
    $totalPendingMigrations = count($pendingMigrations);
    $totalMigrationsToRun = min($runs ?: $totalPendingMigrations, $totalPendingMigrations);
    $pendingMigrations = array_slice($pendingMigrations, 0, $totalMigrationsToRun);

    $totalRowsAffected = 0;
    $progressBar = new ProgressBar($this->output, $totalMigrationsToRun);

    $progressBar->start();
    # Foreach migration in the pending migrations
    foreach ($pendingMigrations as $index => $migration) {
      $progressBar->setMessage("Running migration $migration");
      # Get the up.sql file content
      $upFilePath = Path::join($this->getMigrationsDirectoryPath(), $migration, 'up.sql');

      if (! file_exists($upFilePath) ) {
        $progressBar->finish();
        $this->output->writeln("<error>The up.sql file for migration $migration does not exist</error>\n");
        return false;
      }
      $upFileContent = file_get_contents($upFilePath);

      # Execute the up.sql file
      if (empty($upFileContent)) {
        $formatter = new FormatterHelper();
        $this->output->writeln("\n" . $formatter->formatBlock("WARNING:", 'comment') . " The up.sql file for migration <comment>$migration</comment> is empty\n", OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }

      if (false === $this->beginTransaction() ) {
        $this->output->writeln("<error>Failed to begin the transaction</error>\n");
        return false;
      }

      try {
        $statement = $this->query($upFileContent);
      } catch (PDOException $exception) {
        if (false === $this->rollBack() ) {
          $this->output->writeln("<error>Failed to roll back the transaction</error>\n");
        }
        throw $exception;
      }

      if ($this->inTransaction() && false === $this->commit() ) {
        $this->output->writeln("<error>Failed to commit the transaction</error>\n");
        return false;
      }

      if (false === $statement) {
        $progressBar->finish();
        $this->output->writeln("<error>Failed to execute the up.sql file for migration $migration</error>\n");
        return false;
      }

      $totalRowsAffected += $statement->rowCount();

      # Update the migrations table
      $migrationsTableName = self::getMigrationsTableName();
      $timestamp = date(DATE_ATOM);
      $sql = "INSERT INTO $migrationsTableName (migration, ran_at) VALUES ('$migration', '$timestamp')";

      if (false === $statement->closeCursor()) {
        $progressBar->finish();
        $this->output->writeln("<error>Failed to close the cursor</error>\n");
        return false;
      }

      $this->beginTransaction();

      try {
        $statement = $this->query($sql);
      } catch (PDOException $exception) {
        if (false === $this->rollBack()) {
          $this->output->writeln("<error>Failed to roll back the transaction</error>\n");
        }
        throw $exception;
      }

      if ($this->inTransaction() && false === $this->commit()) {
        $this->output->writeln("<error>Failed to commit the transaction</error>\n");
        return false;
      }

      if (false === $statement) {
        $progressBar->finish();
        $this->output->writeln("<error>Failed to update the migrations table for migration $migration</error>\n");
        return false;
      }

      $successfulRuns++;

      if ($index === $totalMigrationsToRun - 1) {
        break;
      }

      $progressBar->advance();
    }
    $progressBar->setMessage("<info>RUN</info> $successfulRuns migrations");
    $progressBar->finish();

    $this->output->writeln("\n<info>$totalRowsAffected rows affected</info>\n", OutputInterface::VERBOSITY_VERBOSE);
    return $successfulRuns;
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function down(?int $rollbacks = null): int|false
  {
    $successfulRollbacks = 0;
    $pendingRollbacks = $this->listRan();

    if (empty($pendingRollbacks)) {
      $this->output->writeln("No pending rollbacks");
      return 0;
    }
    $totalRanMigrations = count($pendingRollbacks);
    $totalMigrationsToRollback = min($rollbacks ?: $totalRanMigrations, $totalRanMigrations);

    $totalRowsAffected = 0;

    $progressBar = new ProgressBar($this->output, $totalMigrationsToRollback);
    $progressBar->start();

    # Foreach migration in the pending migrations
    foreach ($pendingRollbacks as $index => $pendingMigration) {
      $migration = $pendingMigration['migration'];
      $progressBar->setMessage("Rolling back migration $migration");

      # Get the down.sql file content
      $downFilePath = Path::join($this->getMigrationsDirectoryPath(), $migration, 'down.sql');

      if (! file_exists($downFilePath) ) {
        $this->output->writeln("<error>The down.sql file for migration $migration does not exist</error>\n");
        $progressBar->finish();
        return false;
      }
      $downFileContent = file_get_contents($downFilePath);

      # Execute the down.sql file
      if (empty($downFileContent)) {
        $formatter = new FormatterHelper();
        $this->output->writeln($formatter->formatBlock("WARNING:", 'comment') . " The down.sql file for migration <comment>$migration</comment> is empty\n", OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }

      if (false === $this->beginTransaction()) {
        $this->output->writeln(" <error>Failed to begin the transaction</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        return false;
      }

      try {
        $statement = $this->query($downFileContent);
      } catch (PDOException $exception) {
        if (false === $this->rollBack()) {
          $this->output->writeln(" <error>Failed to roll back the transaction</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        }
        throw $exception;
      }

      if ($this->inTransaction() && false === $this->commit()) {
        $this->output->writeln(" <error>Failed to commit the transaction</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        return false;
      }

      if (false === $statement) {
        $this->output->writeln(" <error>Failed to execute the down.sql file for migration $migration</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        $progressBar->finish();
        return false;
      }

      $totalRowsAffected += $statement->rowCount();

      # Update the migrations table
      $migrationsTableName = self::getMigrationsTableName();
      $sql = "DELETE FROM $migrationsTableName WHERE migration='$migration'";

      if (false === $statement->closeCursor()) {
        $this->output->writeln(" <error>Failed to close the cursor</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        return false;
      }

      $this->beginTransaction();

      try {
        $statement = $this->query($sql);
      } catch (PDOException $exception) {
        if (false === $this->rollBack()) {
          $this->output->writeln(" <error>Failed to roll back the transaction</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        }
        throw $exception;
      }

      if ($this->inTransaction() && false === $this->commit()) {
        $this->output->writeln(" <error>Failed to commit the transaction</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        return false;
      }

      if (false === $statement) {
        $this->output->writeln(" <error>Failed to update the migrations table for migration $migration</error>\n", OutputInterface::VERBOSITY_VERBOSE);
        return false;
      }

      $successfulRollbacks++;

      if ($index === $totalMigrationsToRollback - 1) {
        break;
      }

      $progressBar->advance();
    }
    $progressBar->setMessage(" <info>ROLLBACK</info> $successfulRollbacks migrations");
    $progressBar->finish();

    $this->output->writeln(" <info>$totalRowsAffected rows affected</info>\n", OutputInterface::VERBOSITY_VERBOSE);
    return $successfulRollbacks;
  }

  /**
   * @inheritDoc
   */
  public function reset(): int|false
  {
    return $this->down(count($this->listRan() ?: []));
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function create(string $name): string|false
  {
    $directoryName = date('YmdHis') . '_' . $name;
    $path = Path::join($this->getMigrationsDirectoryPath(), DatabaseType::MYSQL->value, $this->name, $directoryName);

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
    /** @var AllMigrationsLister $lister */
    $lister = $this->getLister(MigrationListerType::ALL);
    return $lister->list();
  }

  /**
   * @inheritDoc
   */
  public function listRan(): array|false
  {
    /** @var RanMigrationsLister $lister */
    $lister = $this->getLister(MigrationListerType::RAN);
    return $lister->list();
  }

  /**
   * @inheritDoc
   */
  public function listPending(): array|false
  {
    /** @var PendingMigrationsLister $lister */
    $lister = $this->getLister(MigrationListerType::PENDING);
    return $lister->list();
  }

  /**
   * @inheritDoc
   */
  public function last(): string|false
  {
    $migrationsTableName = self::getMigrationsTableName();
    $query = "SELECT migration FROM $migrationsTableName ORDER BY ran_at DESC LIMIT 1";
    $statement = $this->query($query);

    if (false === $statement)
    {
      $this->output->writeln('<error>Failed to get the last migration</error>\n');
      return false;
    }

    $result = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (! isset($result[0]) ) {
      $this->output->writeln('<error>Failed to get the last migration</error>\n');
      return false;
    }

    return $result[0]['migration'] ?? '';
  }

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function next(): string|false
  {
    $lastMigration = $this->last();

    $allMigrations = $this->listAll();

    $lastMigrationIndex = array_search($lastMigration, $allMigrations ?: []);

    if (false === $lastMigrationIndex)
    {
      $this->output->writeln('<error>Failed to get the next migration</error>\n');
      return false;
    }

    if (is_string($lastMigrationIndex))
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
    return Path::join(getcwd() ?: '', 'migrations', DatabaseType::MYSQL->value, $this->name);
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