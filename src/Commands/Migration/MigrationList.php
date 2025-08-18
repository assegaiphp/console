<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Migrations\Enumerations\MigrationListerType;
use Assegai\Console\Core\Migrations\Interfaces\MigrationListerInterface;
use Assegai\Console\Core\Migrations\Interfaces\MigratorInterface;
use Assegai\Console\Core\Migrations\MySQLDatabaseMigrator;
use Assegai\Console\Core\Migrations\PostgreSQLDatabaseMigrator;
use Assegai\Console\Core\Migrations\SQLiteDatabaseMigrator;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'migration:list',
  description: 'List all migrations',
  aliases: ['m:list', 'migrations']
)]
class MigrationList extends Command
{
  /**
   * @var string[] $migrationStatuses The list types
   */
  protected array $migrationStatuses = ['all', 'pending', 'executed'];

  /**
   * @var MigrationListerInterface|null $migrationLister The migration lister
   */
  protected ?MigrationListerInterface $migrationLister = null;

  public function configure(): void
  {
    $this->addArgument(ParameterKey::DB_NAME->value, InputArgument::OPTIONAL, 'The name of the database')
      ->addOption(ParameterKey::DB_TYPE->value, ParameterKey::DB_TYPE->getShortName(), InputArgument::OPTIONAL, 'The type of the database', DEFAULT_DATABASE_TYPE, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use MySQL database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use SQLite database')
      ->addOption('status', 's', InputArgument::OPTIONAL, 'Filter migration status', 'all', $this->migrationStatuses)
      ->addOption('all', null, InputOption::VALUE_NONE, 'List all migrations')
      ->addOption('pending', null, InputOption::VALUE_NONE, 'List pending migrations')
      ->addOption('executed', null, InputOption::VALUE_NONE, 'List executed migrations')
      ->setHelp(<<<HELP
This command lists all migrations in the database:

List type options:
  ┌───────────────┬──────────────────────────────┐
  │ <fg=blue>Type</>          │ <fg=blue>Description</>                  │
  ├───────────────┼──────────────────────────────┤
  │ <info>all</info>           │ List all migrations          │
  │ <info>pending</info>       │ List pending migrations      │
  │ <info>executed</info>      │ List executed migrations     │
  └───────────────┴──────────────────────────────┘
HELP);
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $databaseType = get_datasource_type($input, $output);

    if (false === $databaseType || ! DatabaseType::isValid($databaseType) ) {
      $output->writeln('<error>Invalid database type</error>');
      return Command::FAILURE;
    }

    $databaseType = DatabaseType::tryFrom($databaseType);

    $status = $input->getOption('status');

    if ($input->getOption(DatabaseType::MYSQL->value)) {
      $databaseType = DatabaseType::MYSQL;
    } elseif ($input->getOption(DatabaseType::POSTGRESQL->value)) {
      $databaseType = DatabaseType::POSTGRESQL;
    } elseif ($input->getOption(DatabaseType::SQLITE->value)) {
      $databaseType = DatabaseType::SQLITE;
    }

    if (! $databaseType) {
      $output->writeln('<error>Invalid database type</error>');
      return Command::FAILURE;
    }

    $databaseName = get_datasource_name($input, $output, $databaseType->value);

    if (false === $databaseName) {
      return Command::FAILURE;
    }

    /** @var MigratorInterface $migrator */
    $migrator = match ($databaseType) {
      DatabaseType::POSTGRESQL => new PostgreSQLDatabaseMigrator($databaseName, $input, $output),
      DatabaseType::SQLITE => new SQLiteDatabaseMigrator($databaseName, $input, $output),
      default => new MySQLDatabaseMigrator($databaseName, $input, $output),
    };

    $status = MigrationListerType::tryFrom($status);

    if ($input->getOption('all')) {
      $status = MigrationListerType::ALL;
    } elseif ($input->getOption('pending')) {
      $status = MigrationListerType::PENDING;
    } elseif ($input->getOption('executed')) {
      $status = MigrationListerType::RAN;
    }

    if (!$status) {
      $output->writeln('<error>Invalid list type</error>');
      return Command::FAILURE;
    }

    $migrations = match($status) {
      MigrationListerType::ALL => $migrator->listAll(),
      MigrationListerType::RAN => array_map(function($ranMigration) {
        return $ranMigration['migration'];
      }, $migrator->listRan() ?: []),
      MigrationListerType::PENDING => $migrator->listPending()
    };
    $ranMigrations = $migrator->listRan();

    $output->writeln(<<<TABLE
 ┌───┬────────────────────────┬──────────────────────────────────────────────┐
 │   │ <fg=blue>Created At</>             │ <fg=blue>Description</>                                  │
 ├───┼────────────────────────┼──────────────────────────────────────────────┤
TABLE
);
    foreach ($migrations ?: [] as $migration) {
      [$createdAt, $name] = explode('_', $migration, 2);
      $createdYear = substr($createdAt, 0, 4);
      $createdMonth = substr($createdAt, 4, 2);
      $createdDay = substr($createdAt, 6, 2);
      $createdHour = substr($createdAt, 8, 2);
      $createdMinute = substr($createdAt, 10, 2);
      $createdSecond = substr($createdAt, 12, 2);

      $createdAt = "$createdYear/$createdMonth/$createdDay $createdHour:$createdMinute:$createdSecond";
      $executed = $this->wasRun($migration, $ranMigrations ?: []) ? "<info>✓</info>" : "<fg=red>✕</>";
      $migrationDescription = new Text($name);
      $formattedDescription = sprintf('%-44s', $migrationDescription->titleCase());
      $output->writeln(<<<TABLE
 │ $executed │ <info>$createdAt</info>    │ <info>$formattedDescription</info> │
TABLE
);
    }

    $output->writeln(' └───┴────────────────────────┴──────────────────────────────────────────────┘');

    return Command::SUCCESS;
  }

  /**
   * Check if a migration was run.
   *
   * @param string $migration The migration
   * @param array<array{migration: string, ranAt: string}> $ranMigrations The ran migrations
   * @return bool True if the migration was run, false otherwise
   */
  public function wasRun(string $migration, array $ranMigrations): bool
  {
    foreach ($ranMigrations as $ranMigration) {
      if ($ranMigration['migration'] === $migration) {
        return true;
      }
    }

    return false;
  }
}