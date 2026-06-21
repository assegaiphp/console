<?php

use Assegai\Console\Core\Migrations\Concerns\ExecutesMigrationScripts;

final class ExposedDrainingMigrationScriptRunner extends PDO
{
  use ExecutesMigrationScripts {
    executeMigrationScript as public runMigrationScript;
  }

  public function __construct()
  {
    parent::__construct("sqlite::memory:");
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  protected function shouldDrainMigrationScriptRowsets(): bool
  {
    return true;
  }
}

it("closes rowset cursors after executing drain-mode migration scripts", function () {
  $runner = new ExposedDrainingMigrationScriptRunner();

  expect($runner->runMigrationScript("SELECT 1 AS value"))->toBe(0);

  $statement = $runner->query("SELECT 2 AS value");

  if (false === $statement) {
    throw new RuntimeException("Failed to run follow-up query.");
  }

  expect((int) $statement->fetchColumn())->toBe(2);
});
