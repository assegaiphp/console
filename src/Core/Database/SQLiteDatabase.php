<?php

namespace Assegai\Console\Core\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\SQLDatabaseConnectionInterface;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Exception;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SQLiteDatabase. This class is a SQLite database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class SQLiteDatabase extends PDO implements SQLDatabaseConnectionInterface
{
  /**
   * @var string $path The path to the database.
   */
  protected string $path = '';

  /**
   * @param string $name
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(
    protected string $name,
    protected InputInterface $input,
    protected OutputInterface $output
  )
  {
    $inspector = new Inspector($this->input, $this->output);

    if (! $inspector->isValidWorkspace(getcwd() ?: '') ) {
      $this->output->writeln('<error>Failed to load SQLite config. Invalid workspace.</error>');
      exit(Command::FAILURE);
    }

    $dbConfig = new DBConfig($this->input, $this->output, $this->name, DatabaseType::SQLITE->value);
    if (Command::SUCCESS !== $dbConfig->load()) {
      $this->output->writeln('<error>Database configuration not found.</error>');
      exit(Command::FAILURE);
    }

    $workingDirectory = Path::getProjectRootPath() ?: Path::getWorkingDirectory() ?: '';
    $configuredPath = $dbConfig->get("sqlite.$this->name.path") ?? exit(Command::FAILURE);
    $this->path = self::normalizePath($configuredPath, $workingDirectory);
    self::ensureParentDirectoryExists($this->path, $this->output);

    $dsn = "sqlite:$this->path";
    parent::__construct($dsn);
  }

  /**
   * @inheritDoc
   */
  public static function exists(string $name): bool
  {
    $input = new MockInput();
    $output = new ConsoleOutput();

    $dbConfig = new DBConfig($input, $output, $name, DatabaseType::SQLITE->value);
    if (Command::SUCCESS !== $dbConfig->load()) {
      $output->writeln('<error>Database configuration not found.</error>');
      return false;
    }

    $path = $dbConfig->get("sqlite.$name.path");

    if (!$path) {
      $output->writeln('<error>Database path not defined.</error>');
      return false;
    }

    $workingDirectory = Path::getProjectRootPath() ?: Path::getWorkingDirectory() ?: '';
    $path = self::normalizePath($path, $workingDirectory);

    if (self::isSpecialPath($path)) {
      return false;
    }

    return file_exists($path);
  }

  /**
   * @inheritDoc
   */
  public static function doesNotExist(string $name): bool
  {
    return ! self::exists($name);
  }

  /**
   * @inheritDoc
   */
  public static function setup(string $name): int
  {
    $input = new MockInput();
    $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
    $type = DatabaseType::SQLITE->value;
    $dbConfig = new DBConfig($input, $output, $name, $type);

    if (Command::SUCCESS !== $dbConfig->load()) {
      $output->writeln("<error>Failed to load database configuration.</error>\n");
      return Command::FAILURE;
    }

    $path = $dbConfig->get("sqlite.$name.path");

    if (!$path) {
      $output->writeln("<error>Database path not defined.</error>\n");
      return Command::FAILURE;
    }

    $workingDirectory = Path::getProjectRootPath() ?: Path::getWorkingDirectory() ?: '';
    $path = self::normalizePath($path, $workingDirectory);

    $migrationsTableName = self::getMigrationsTableName();
    $query = "CREATE TABLE $migrationsTableName (migration TEXT PRIMARY KEY, ran_at TEXT)";

    try {
      $database = new self($name, $input, $output);

      if (false === $database->exec($query)) {
        $output->writeln("<error>Failed to create the migrations table.</error>\n");
        return Command::FAILURE;
      }
    } catch (Exception $exception) {
      if (! str_contains($exception->getMessage(), 'already exists')) {
        $output->writeln("<error>({$exception->getCode()}): {$exception->getMessage()}</error>\n");
        return Command::FAILURE;
      }

      $output->writeln("<comment>Migrations table already exists.</comment>\n");
      return Command::SUCCESS;
    }

    $output->writeln("<info>SQLite database successfully set up.</info>\n", OutputInterface::VERBOSITY_VERBOSE);
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function drop(): int
  {
    return unlink($this->path) ? Command::SUCCESS : Command::FAILURE;
  }

  /**
   * @inheritDoc
   */
  public static function getMigrationsTableName(): string
  {
    return '__migrations';
  }

  /**
   * @inheritDoc
   */
  public function hasTable(string $tableName): bool
  {
    $query = "SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'";

    $statement = $this->query($query);

    if (false === $statement) {
      $this->output->writeln("<error>Failed to check if table exists.</error>\n");
      return false;
    }

    return $statement->fetchColumn() === $tableName;
  }

  /**
   * @inheritDoc
   */
  public function createMigrationsTable(): int
  {
    $migrationsTableName = self::getMigrationsTableName();
    $query = "CREATE TABLE IF NOT EXISTS $migrationsTableName (migration TEXT PRIMARY KEY, ran_at TEXT)";

    if (false === $this->exec($query)) {
      $this->output->writeln("<error>Failed to create the migrations table.</error>\n");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  public static function normalizePath(string $path, ?string $workingDirectory = null): string
  {
    if (str_starts_with($path, 'sqlite:')) {
      $path = substr($path, strlen('sqlite:'));
    }

    if ($path === '') {
      return ':memory:';
    }

    if (self::isSpecialPath($path)) {
      return $path;
    }

    if (self::isAbsolutePath($path)) {
      return Path::normalize($path);
    }

    $workingDirectory ??= Path::getProjectRootPath() ?: Path::getWorkingDirectory() ?: '';

    return Path::join($workingDirectory, $path);
  }

  private static function ensureParentDirectoryExists(string $path, OutputInterface $output): void
  {
    if (self::isSpecialPath($path)) {
      return;
    }

    $directory = dirname($path);

    if ($directory === '' || $directory === '.' || is_dir($directory)) {
      return;
    }

    if (false === mkdir($directory, 0777, true) && ! is_dir($directory)) {
      $output->writeln('<error>Failed to create data directory.</error>');
      exit(Command::FAILURE);
    }
  }

  private static function isSpecialPath(string $path): bool
  {
    return $path === ':memory:' || str_starts_with($path, 'file:');
  }

  private static function isAbsolutePath(string $path): bool
  {
    return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\/]#', $path) === 1;
  }
}
