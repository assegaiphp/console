<?php

namespace Assegai\Console\Core\Migrations\Concerns;

use PDO;
use PDOException;
use PDOStatement;

trait ExecutesMigrationScripts
{
  abstract public function exec(string $statement): int|false;

  abstract public function query(string $query, int $fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE, mixed ...$fetch_mode_args): PDOStatement|false;

  protected function executeMigrationScript(string $script): int|false
  {
    if ($this->shouldDrainMigrationScriptRowsets()) {
      return $this->executeMigrationScriptAndDrainRowsets($script);
    }

    return $this->exec($script);
  }

  protected function shouldDrainMigrationScriptRowsets(): bool
  {
    return false;
  }

  private function executeMigrationScriptAndDrainRowsets(string $script): int|false
  {
    $statement = $this->query($script);

    if (false === $statement) {
      return false;
    }

    $rowsAffected = 0;

    do {
      $rowCount = $statement->rowCount();

      if ($rowCount > 0) {
        $rowsAffected += $rowCount;
      }

      if ($statement->columnCount() > 0) {
        while (false !== $statement->fetch(PDO::FETCH_NUM)) {
        }
      }
    } while ($this->advanceMigrationScriptRowset($statement));

    if (false === $statement->closeCursor()) {
      return false;
    }

    return $rowsAffected;
  }

  private function advanceMigrationScriptRowset(PDOStatement $statement): bool
  {
    try {
      return $statement->nextRowset();
    } catch (PDOException $exception) {
      if ($exception->getCode() === "IM001") {
        return false;
      }

      throw $exception;
    }
  }
}
