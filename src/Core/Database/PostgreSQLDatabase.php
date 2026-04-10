<?php

namespace Assegai\Console\Core\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\SQLDatabaseConnectionInterface;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Inspector;
use PDO;
use PDOException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PostgreSQLDatabase. This class is a PostgreSQL database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class PostgreSQLDatabase extends PDO implements SQLDatabaseConnectionInterface
{
  protected Inspector $inspector;

  public function __construct(
    protected string $name,
    protected InputInterface $input,
    protected OutputInterface $output
  )
  {
    $this->inspector = new Inspector($this->input, $this->output);

    if (! $this->inspector->isValidWorkspace(getcwd() ?: '')) {
      $message = 'Failed to load PostgreSQL config. Invalid workspace.';
      $this->output->writeln("<error>$message</error>");
      throw new RuntimeException($message);
    }

    $config = self::loadConnectionConfig($this->name, $this->input, $this->output)
      ?? throw new RuntimeException("Database config for {$this->name} not found.");

    try {
      parent::__construct(
        self::buildDsn($config['host'], $config['port'], $this->name),
        $config['username'],
        $config['password'],
        self::getDefaultPdoOptions()
      );
    } catch (PDOException $exception) {
      $message = $exception->getMessage();
      $this->output->writeln("<error>$message</error>");
      throw new RuntimeException($message, (int) $exception->getCode(), $exception);
    }
  }

  public static function exists(string $name): bool
  {
    $input = new MockInput();
    $output = new ConsoleOutput();
    $config = self::loadConnectionConfig($name, $input, $output);

    if ($config === null) {
      return false;
    }

    try {
      $connection = self::createAdministrationConnection($config, self::getMaintenanceDatabaseName($name));
      $exists = self::databaseExists($connection, $name);
      $connection = null;

      return $exists;
    } catch (PDOException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return false;
    }
  }

  public static function doesNotExist(string $name): bool
  {
    return ! self::exists($name);
  }

  public static function setup(string $name): int
  {
    $input = new MockInput();
    $output = new ConsoleOutput();
    $config = self::loadConnectionConfig($name, $input, $output);

    if ($config === null) {
      return Command::FAILURE;
    }

    try {
      $adminConnection = self::createAdministrationConnection($config, self::getMaintenanceDatabaseName($name));

      if (! self::databaseExists($adminConnection, $name)) {
        $result = $adminConnection->exec(self::buildCreateDatabaseSql($name));

        if ($result === false) {
          $output->writeln("<error>Failed to create the database.</error>\n");
          return Command::FAILURE;
        }
      } else {
        $output->writeln("<comment>Database $name already exists.</comment>\n");
      }

      $adminConnection = null;

      $database = new self($name, $input, $output);

      if (Command::SUCCESS !== $database->createMigrationsTable()) {
        return Command::FAILURE;
      }

      $output->writeln("<info>PostgreSQL database successfully set up.</info>\n", OutputInterface::VERBOSITY_VERBOSE);
      return Command::SUCCESS;
    } catch (RuntimeException|PDOException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . "</error>\n");
      return Command::FAILURE;
    }
  }

  public function drop(): int
  {
    try {
      $config = self::loadConnectionConfig($this->name, $this->input, $this->output)
        ?? throw new RuntimeException("Database config for {$this->name} not found.");
      $adminConnection = self::createAdministrationConnection($config, self::getMaintenanceDatabaseName($this->name));

      self::terminateActiveConnections($adminConnection, $this->name);

      $result = $adminConnection->exec(self::buildDropDatabaseSql($this->name));
      $adminConnection = null;

      if ($result === false) {
        $this->output->writeln('<error>Failed to drop the database.</error>');
        return Command::FAILURE;
      }

      $this->output->writeln('<info>Database dropped.</info>');
      return Command::SUCCESS;
    } catch (RuntimeException|PDOException $exception) {
      $this->output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

  public static function getMigrationsTableName(): string
  {
    return '__migrations';
  }

  public function hasTable(string $tableName): bool
  {
    $statement = $this->prepare(
      "SELECT EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = current_schema()
          AND table_name = :table
      )"
    );

    if (false === $statement) {
      $this->output->writeln("<error>Failed to check if the table exists.</error>\n");
      return false;
    }

    $statement->execute(['table' => $tableName]);

    return (bool) $statement->fetchColumn();
  }

  public function createMigrationsTable(): int
  {
    $migrationsTableName = self::quoteIdentifier(self::getMigrationsTableName());
    $query = "CREATE TABLE IF NOT EXISTS $migrationsTableName (
      migration VARCHAR(255) NOT NULL PRIMARY KEY,
      ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $result = $this->exec($query);

    if (false === $result) {
      $this->output->writeln("<error>Failed to create the migrations table.</error>\n");
      return Command::FAILURE;
    }

    $this->output->writeln("<info>Migrations table created.</info>\n");
    return Command::SUCCESS;
  }

  /**
   * @return array{host:string,port:int,username:string,password:string}|null
   */
  private static function loadConnectionConfig(string $name, InputInterface $input, OutputInterface $output): ?array
  {
    $inspector = new Inspector($input, $output);
    $workingDirectory = getcwd() ?: '';

    if (! $inspector->isValidWorkspace($workingDirectory)) {
      $output->writeln('<error>This is not a valid workspace.</error>');
      return null;
    }

    $type = DatabaseType::POSTGRESQL->value;
    $dbConfig = new DBConfig($input, $output, $name, $type);

    if (Command::SUCCESS !== $dbConfig->load()) {
      $output->writeln('<error>Failed to load database configuration.</error>');
      return null;
    }

    $configPath = "$type.$name";

    if (is_null($dbConfig->get($configPath))) {
      $output->writeln([
        "<error>Database config for $name not found.</error>",
        "\n<comment>Run `assegai database:configure $name --pgsql` to configure the database.</comment>"
      ]);
      return null;
    }

    return [
      'host' => (string) $dbConfig->get("$configPath.host", DEFAULT_POSTGRES_HOST),
      'port' => (int) $dbConfig->get("$configPath.port", DEFAULT_POSTGRES_PORT),
      'username' => (string) ($dbConfig->get("$configPath.username") ?? $dbConfig->get("$configPath.user", DEFAULT_POSTGRES_USER)),
      'password' => (string) ($dbConfig->get("$configPath.password") ?? ''),
    ];
  }

  /**
   * @param array{host:string,port:int,username:string,password:string} $config
   */
  private static function createAdministrationConnection(array $config, string $databaseName): PDO
  {
    return new PDO(
      self::buildDsn($config['host'], $config['port'], $databaseName),
      $config['username'],
      $config['password'],
      self::getDefaultPdoOptions()
    );
  }

  private static function databaseExists(PDO $connection, string $databaseName): bool
  {
    $statement = $connection->prepare('SELECT 1 FROM pg_database WHERE datname = :database LIMIT 1');
    $statement->execute(['database' => $databaseName]);

    return $statement->fetchColumn() !== false;
  }

  private static function terminateActiveConnections(PDO $connection, string $databaseName): void
  {
    $statement = $connection->prepare(
      'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :database AND pid <> pg_backend_pid()'
    );
    $statement->execute(['database' => $databaseName]);
  }

  private static function buildDsn(string $host, int $port, string $databaseName): string
  {
    return "pgsql:host=$host;port=$port;dbname=$databaseName";
  }

  private static function getMaintenanceDatabaseName(string $databaseName): string
  {
    return $databaseName === 'postgres' ? 'template1' : 'postgres';
  }

  /**
   * @return array<int, mixed>
   */
  private static function getDefaultPdoOptions(): array
  {
    return [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_STRINGIFY_FETCHES => false,
    ];
  }

  private static function buildCreateDatabaseSql(string $name): string
  {
    return 'CREATE DATABASE ' . self::quoteIdentifier($name) . ';';
  }

  private static function buildDropDatabaseSql(string $name): string
  {
    return 'DROP DATABASE IF EXISTS ' . self::quoteIdentifier($name) . ';';
  }

  private static function quoteIdentifier(string $identifier): string
  {
    return '"' . str_replace('"', '""', $identifier) . '"';
  }
}
