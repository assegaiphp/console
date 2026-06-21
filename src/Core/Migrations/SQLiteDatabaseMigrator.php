<?php /** @noinspection PhpSuperClassIncompatibleWithInterfaceInspection */

namespace Assegai\Console\Core\Migrations;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Core\Migrations\Concerns\ExecutesMigrationScripts;
use Assegai\Console\Core\Migrations\Enumerations\MigrationListerType;
use Assegai\Console\Core\Migrations\Interfaces\MigrationListerInterface;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Assegai\Console\Core\Migrations\Listers\AllMigrationsLister;
use Assegai\Console\Core\Migrations\Listers\PendingMigrationsLister;
use Assegai\Console\Core\Migrations\Listers\RanMigrationsLister;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class SQLiteDatabaseMigrator. This class is a migrator for SQLite databases.
 *
 * @package Assegai\Console\Core\Migrations
 */
class SQLiteDatabaseMigrator extends SQLiteDatabase implements MigratorInterface
{
  use ExecutesMigrationScripts;

  /**
   * SQLite statements that must be run without an explicit transaction.
   *
   * VACUUM is a top-level statement. These PRAGMAs either fail inside a
   * transaction or silently do not take effect there.
   */
  private const SQLITE_AUTOCOMMIT_PRAGMAS = [
    'FOREIGN_KEYS' => true,
    'JOURNAL_MODE' => true,
    'WAL_CHECKPOINT' => true,
  ];

  /**
   * @inheritDoc
   * @noinspection DuplicatedCode
   */
  public function up(?int $runs = null): int|false
  {
    $successfulRuns = 0;

    $pendingMigrations = $this->listPending();
    $totalPendingMigrations = count($pendingMigrations ?: []);
    $totalMigrationsToRun = min($runs ?: $totalPendingMigrations, $totalPendingMigrations);

    $totalRowsAffected = 0;

    # Foreach migration in the pending migrations
    foreach ($pendingMigrations ?: [] as $index => $migration)
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
      if (empty($upFileContent))
      {
        $formatter = new FormatterHelper();
        $this->output->writeln("\n" . $formatter->formatBlock("WARNING:", 'comment') . " The up.sql file for migration <comment>$migration</comment> is empty\n", OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }
      $migrationsTableName = self::getMigrationsTableName();
      $timestamp = date(DATE_ATOM);
      $sql = "INSERT INTO $migrationsTableName (migration, ran_at) VALUES ({$this->quote($migration)}, {$this->quote($timestamp)})";
      $rowsAffected = $this->executeMigrationAndRecord($upFileContent, $sql, $migration);

      if (false === $rowsAffected)
      {
        return false;
      }

      $totalRowsAffected += $rowsAffected;

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
    $totalRanMigrations = count($pendingMigrations ?: []);
    $totalMigrationsToRollback = min($rollbacks ?: $totalRanMigrations, $totalRanMigrations);

    $totalRowsAffected = 0;

    # Foreach migration in the pending migrations
    foreach ($pendingMigrations ?: [] as $index => $pendingMigration)
    {
      $migration = $pendingMigration['migration'];
      # Get the down.sql file content
      $downFilePath = Path::join($this->getMigrationsDirectoryPath(), $migration, 'down.sql');

      if (! file_exists($downFilePath) )
      {
        $this->output->writeln("<error>The down.sql file for migration $migration does not exist</error>\n");
        return false;
      }
      $downFileContent = file_get_contents($downFilePath);

      # Execute the down.sql file
      if (empty($downFileContent))
      {
        $formatter = new FormatterHelper();
        $this->output->writeln("\n" . $formatter->formatBlock("WARNING:", 'comment') . " The down.sql file for migration <comment>$migration</comment> is empty\n", OutputInterface::VERBOSITY_VERBOSE);
        continue;
      }
      $migrationsTableName = self::getMigrationsTableName();
      $sql = "DELETE FROM $migrationsTableName WHERE migration={$this->quote($migration)}";
      $rowsAffected = $this->executeMigrationAndRecord($downFileContent, $sql, $migration);

      if (false === $rowsAffected)
      {
        return false;
      }

      $totalRowsAffected += $rowsAffected;

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
    return $this->down(count($this->listRan() ?: []));
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
   * @noinspection DuplicatedCode
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

    $result = $statement->fetchAll();

    if (!isset($result[0])) {
      $this->output->writeln('<error>Failed to get the last migration</error>\n');
      return false;
    }

    return  $result[0]['migration'] ?? '';
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
   * Runs a migration script and records its migration-table change.
   */
  private function executeMigrationAndRecord(string $script, string $migrationTableSql, string $migration): int|false
  {
    if ($this->migrationScriptRequiresAutocommit($script)) {
      return $this->executeMigrationWithoutTransaction($script, $migrationTableSql, $migration);
    }

    return $this->executeMigrationInTransaction($script, $migrationTableSql, $migration);
  }

  /**
   * Runs a transaction-safe migration script and records its migration-table change atomically.
   */
  private function executeMigrationInTransaction(string $script, string $migrationTableSql, string $migration): int|false
  {
    if (false === $this->beginTransaction()) {
      $this->output->writeln("<error>Failed to begin the transaction for migration $migration</error>\n");
      return false;
    }

    try {
      $rowsAffected = $this->executeMigrationScript($script);

      if (false === $rowsAffected) {
        $this->rollBackMigrationTransaction($migration);
        $this->output->writeln("<error>Failed to execute the SQL file for migration $migration</error>\n");
        return false;
      }

      if (false === $this->exec($migrationTableSql)) {
        $this->rollBackMigrationTransaction($migration);
        $this->output->writeln("<error>Failed to update the migrations table for migration $migration</error>\n");
        return false;
      }

      if (false === $this->commit()) {
        $this->rollBackMigrationTransaction($migration);
        $this->output->writeln("<error>Failed to commit the transaction for migration $migration</error>\n");
        return false;
      }

      return $rowsAffected;
    } catch (Throwable $exception) {
      $this->rollBackMigrationTransaction($migration);
      throw $exception;
    }
  }

  /**
   * Runs SQLite statements that are not valid inside an explicit transaction.
   */
  private function executeMigrationWithoutTransaction(string $script, string $migrationTableSql, string $migration): int|false
  {
    $rowsAffected = $this->executeMigrationScript($script);

    if (false === $rowsAffected) {
      $this->output->writeln("<error>Failed to execute the SQL file for migration $migration</error>\n");
      return false;
    }

    if (false === $this->exec($migrationTableSql)) {
      $this->output->writeln("<error>Failed to update the migrations table for migration $migration</error>\n");
      return false;
    }

    return $rowsAffected;
  }

  private function rollBackMigrationTransaction(string $migration): void
  {
    if (! $this->inTransaction()) {
      return;
    }

    try {
      if (false === $this->rollBack()) {
        $this->output->writeln("<error>Failed to roll back the transaction for migration $migration</error>\n");
      }
    } catch (Throwable) {
      $this->output->writeln("<error>Failed to roll back the transaction for migration $migration</error>\n");
    }
  }

  private function migrationScriptRequiresAutocommit(string $script): bool
  {
    foreach ($this->splitSqlStatements($script) as $statement) {
      if ($this->sqliteStatementRequiresAutocommit($statement)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @return list<string>
   */
  private function splitSqlStatements(string $script): array
  {
    $statements = [];
    $start = 0;
    $length = strlen($script);
    $singleQuoted = false;
    $doubleQuoted = false;
    $backtickQuoted = false;
    $bracketQuoted = false;
    $lineComment = false;
    $blockComment = false;

    for ($index = 0; $index < $length; $index++) {
      $character = $script[$index];
      $nextCharacter = $script[$index + 1] ?? '';

      if ($lineComment) {
        if ($character === "\n") {
          $lineComment = false;
        }
        continue;
      }

      if ($blockComment) {
        if ($character === '*' && $nextCharacter === '/') {
          $blockComment = false;
          $index++;
        }
        continue;
      }

      if ($singleQuoted) {
        if ($character === "'" && $nextCharacter === "'") {
          $index++;
          continue;
        }
        if ($character === "'") {
          $singleQuoted = false;
        }
        continue;
      }

      if ($doubleQuoted) {
        if ($character === '"' && $nextCharacter === '"') {
          $index++;
          continue;
        }
        if ($character === '"') {
          $doubleQuoted = false;
        }
        continue;
      }

      if ($backtickQuoted) {
        if ($character === '`' && $nextCharacter === '`') {
          $index++;
          continue;
        }
        if ($character === '`') {
          $backtickQuoted = false;
        }
        continue;
      }

      if ($bracketQuoted) {
        if ($character === ']') {
          $bracketQuoted = false;
        }
        continue;
      }

      if ($character === '-' && $nextCharacter === '-') {
        $lineComment = true;
        $index++;
        continue;
      }

      if ($character === '/' && $nextCharacter === '*') {
        $blockComment = true;
        $index++;
        continue;
      }

      if ($character === "'") {
        $singleQuoted = true;
        continue;
      }

      if ($character === '"') {
        $doubleQuoted = true;
        continue;
      }

      if ($character === '`') {
        $backtickQuoted = true;
        continue;
      }

      if ($character === '[') {
        $bracketQuoted = true;
        continue;
      }

      if ($character !== ';') {
        continue;
      }

      $statement = trim(substr($script, $start, $index - $start));

      if ($statement !== '') {
        $statements[] = $statement;
      }

      $start = $index + 1;
    }

    $statement = trim(substr($script, $start));

    if ($statement !== '') {
      $statements[] = $statement;
    }

    return $statements;
  }

  private function sqliteStatementRequiresAutocommit(string $statement): bool
  {
    $offset = 0;
    $firstIdentifier = $this->readSQLiteIdentifier($statement, $offset);

    if ($firstIdentifier === 'VACUUM') {
      return true;
    }

    if ($firstIdentifier !== 'PRAGMA') {
      return false;
    }

    $pragmaName = $this->readSQLiteIdentifier($statement, $offset);

    if ($pragmaName === null) {
      return false;
    }

    $this->skipSQLiteTrivia($statement, $offset);

    if (($statement[$offset] ?? '') === '.') {
      $offset++;
      $schemaQualifiedPragmaName = $this->readSQLiteIdentifier($statement, $offset);

      if ($schemaQualifiedPragmaName !== null) {
        $pragmaName = $schemaQualifiedPragmaName;
      }
    }

    return isset(self::SQLITE_AUTOCOMMIT_PRAGMAS[$pragmaName]);
  }

  private function readSQLiteIdentifier(string $statement, int &$offset): ?string
  {
    $this->skipSQLiteTrivia($statement, $offset);

    $length = strlen($statement);

    if ($offset >= $length) {
      return null;
    }

    $character = $statement[$offset];

    if ($character === '"') {
      return $this->readSQLiteQuotedIdentifier($statement, $offset, '"', '"');
    }

    if ($character === '`') {
      return $this->readSQLiteQuotedIdentifier($statement, $offset, '`', '`');
    }

    if ($character === '[') {
      return $this->readSQLiteQuotedIdentifier($statement, $offset, '[', ']');
    }

    if (! preg_match('/[A-Za-z_]/', $character)) {
      return null;
    }

    $start = $offset;
    $offset++;

    while ($offset < $length && preg_match('/[A-Za-z0-9_]/', $statement[$offset])) {
      $offset++;
    }

    return strtoupper(substr($statement, $start, $offset - $start));
  }

  private function readSQLiteQuotedIdentifier(string $statement, int &$offset, string $openingQuote, string $closingQuote): string
  {
    $offset++;
    $start = $offset;
    $length = strlen($statement);
    $identifier = '';

    while ($offset < $length) {
      $character = $statement[$offset];
      $nextCharacter = $statement[$offset + 1] ?? '';

      if ($character === $closingQuote) {
        if ($openingQuote === $closingQuote && $nextCharacter === $closingQuote) {
          $identifier .= substr($statement, $start, $offset - $start + 1);
          $offset += 2;
          $start = $offset;
          continue;
        }

        $identifier .= substr($statement, $start, $offset - $start);
        $offset++;
        return strtoupper($identifier);
      }

      $offset++;
    }

    return strtoupper($identifier . substr($statement, $start));
  }

  private function skipSQLiteTrivia(string $statement, int &$offset): void
  {
    $length = strlen($statement);

    while ($offset < $length) {
      if (ctype_space($statement[$offset])) {
        $offset++;
        continue;
      }

      if ($statement[$offset] === '-' && ($statement[$offset + 1] ?? '') === '-') {
        $offset += 2;

        while ($offset < $length && $statement[$offset] !== "\n") {
          $offset++;
        }

        continue;
      }

      if ($statement[$offset] === '/' && ($statement[$offset + 1] ?? '') === '*') {
        $offset += 2;

        while ($offset < $length - 1) {
          if ($statement[$offset] === '*' && $statement[$offset + 1] === '/') {
            $offset += 2;
            break;
          }

          $offset++;
        }

        continue;
      }

      return;
    }
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
