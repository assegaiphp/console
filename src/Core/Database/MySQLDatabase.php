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
 * Class MySQLDatabase. This class is a MySQL database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class MySQLDatabase extends PDO implements SQLDatabaseConnectionInterface
{
  /**
   * @var Inspector
   */
  protected Inspector $inspector;

  const int ERROR_UNKNOWN_DATABASE = 1049;
  const int ERROR_INVALID_CREDENTIALS = 1045;

  /**
   * MySQLDatabase constructor.
   *
   * @param string $name The name of the database
   * @param InputInterface $input The input interface
   * @param OutputInterface $output
   */
  public function __construct(
    protected string $name,
    protected InputInterface $input,
    protected OutputInterface $output
  )
  {
    $this->inspector = new Inspector($this->input, $this->output);

    if (! $this->inspector->isValidWorkspace(getcwd() ?: '') )
    {
      $this->output->writeln('<error>Failed to load MySQL config. Invalid workspace.</error>');
      exit(Command::FAILURE);
    }

    $type = DatabaseType::MYSQL->value;
    $dbConfig = new DBConfig($this->input, $this->output, $this->name, $type);
    $dbConfig->load();
    $configPath = "$type.$this->name";

    $host = $dbConfig->get("$configPath.host") ?? DEFAULT_MYSQL_HOST;
    $username = $dbConfig->get("$configPath.username") ?? $dbConfig->get("$configPath.user") ?? '';
    $password = $dbConfig->get("$configPath.password") ?? '';
    $port = $dbConfig->get("$configPath.port") ?? DEFAULT_MYSQL_PORT;

    $dsn = "mysql:host=$host;port=$port;dbname=$this->name";

    try
    {
      parent::__construct($dsn, $username, $password);
    }
    catch (Exception $exception)
    {
      $message = match((int)$exception->getCode()) {
        self::ERROR_UNKNOWN_DATABASE => 'Database not found',
        self::ERROR_INVALID_CREDENTIALS => 'Invalid credentials',
        default => $exception->getMessage()
      };

      $this->output->writeln("<error>({$exception->getCode()}): $message</error>");
      exit(Command::FAILURE);
    }
  }

  /**
   * @inheritDoc
   */
  public static function exists(string $name): bool
  {
    $input = new MockInput();
    $output = new ConsoleOutput();
    $type = DatabaseType::MYSQL->value;

    try
    {
      $inspector = new Inspector($input, $output);
      $workingDirectory = getcwd() ?: '';

      if (! $inspector->isValidWorkspace($workingDirectory) )
      {
        $output->writeln('<error>This is not a valid workspace.</error>');
        return false;
      }

      $dbConfig = new DBConfig($input, $output, $name, $type);
      $dbConfig->load();
      $configPath = "$type.$name";

      if ( is_null($dbConfig->get($configPath)) )
      {
        $output->writeln([
          "<error>Database config for $name not found.</error>",
          "\n<comment>Run `assegai database:configure $name` to configure the database.</comment>"
        ]);
        exit(Command::FAILURE);
      }

      $errorOutputPath = Path::join($workingDirectory, time() . '.error.log');
      $user = $dbConfig->get("$configPath.username") ?? $dbConfig->get("$configPath.user", DEFAULT_MYSQL_USER);
      $password = $dbConfig->get("$configPath.password") ?? '';
      $host = $dbConfig->get("$configPath.host", DEFAULT_MYSQL_HOST);
      $port = $dbConfig->get("$configPath.port", DEFAULT_MYSQL_PORT);

      $result = @`mysql -u $user -p$password -h $host -P $port -e "SHOW DATABASES LIKE '$name';" 2>$errorOutputPath`;

      // Scan the error output for errors. If there are any, log them otherwise delete the log file
      $errorCount = self::scanErrorOutput($errorOutputPath, $output);

      if (false === $errorCount)
      {
        $output->writeln("<error>Error scanning $errorOutputPath file</error>");
        return false;
      }

      if ($errorCount)
      {
        $output->writeln("<error>Errors found! Check $errorOutputPath for more details.</error>");
        return false;
      }

      if (false === unlink($errorOutputPath))
      {
        $output->writeln("<error>Error deleting $errorOutputPath file</error>");
        return false;
      }

      $database = preg_grep("/$name/", explode("\n", $result));
      return ! empty($database);
    }
    catch (Exception)
    {
      $output->writeln('<error>Failed to check if the database exists.</error>');
      return false;
    }
  }

  /**
   * @inheritDoc
   */
  public static function doesNotExist(string $name): bool
  {
    return ! self::exists($name);
  }

  /**
   * Scans the error output for errors.
   *
   * @param string $errorOutputPath The path to the error output file
   * @param OutputInterface $output The output interface
   * @return false|int
   */
  private static function scanErrorOutput(string $errorOutputPath, OutputInterface $output): false|int
  {
    $errorOutput = file($errorOutputPath);
    $errorsFound = 0;

    if (false === $errorOutput)
    {
      $output->writeln("<error>Error reading $errorOutputPath file</error>");
      return false;
    }

    foreach ($errorOutput as $line)
    {
      $matchResult = preg_match('/ERROR/', $line);

      if (false === $matchResult)
      {
        $output->writeln("<error>Error scanning $errorOutputPath file</error>");
        return false;
      }

      if ($matchResult)
      {
        $output->writeln("<error>$line</error>");
        $errorsFound++;
      }
    }

    return $errorsFound;
  }

  /**
   * @inheritDoc
   */
  public static function setup(?string $name = null): int
  {
    $input = new MockInput();
    $output = new ConsoleOutput();

    $type = DatabaseType::MYSQL->value;
    $dbConfig = new DBConfig($input, $output, $name, $type);
    if (Command::SUCCESS !== $dbConfig->load())
    {
      $output->writeln('<error>Failed to load database configuration.</error>');
      return Command::FAILURE;
    }

    $host = $dbConfig->get("$type.$name.host", DEFAULT_MYSQL_HOST);
    $port = $dbConfig->get("$type.$name.port", DEFAULT_MYSQL_PORT);
    $username = $dbConfig->get("$type.$name.username") ?? $dbConfig->get("$type.$name.user", DEFAULT_MYSQL_USER);
    $password = $dbConfig->get("$type.$name.password") ?? '';

    if (! self::exists($name))
    {
      $workingDirectory = getcwd() ?: '';
      $errorOutputPath = Path::join($workingDirectory, time() . '.error.log');
      $createResult = @`mysql -u $username -p$password -h $host -P $port -e "CREATE DATABASE $name;" 2>$errorOutputPath`;

      if (false === $createResult)
      {
        $output->writeln("<error>Failed to create the database.</error>\n");
        return Command::FAILURE;
      }

      $errorCount = self::scanErrorOutput($errorOutputPath, $output);

      if (false === $errorCount)
      {
        $output->writeln("<error>Error scanning $errorOutputPath file</error>\n");
        return Command::FAILURE;
      }

      if ($errorCount)
      {
        $output->writeln("<error>Errors found! Check $errorOutputPath for more details.</error>\n");
        return Command::FAILURE;
      }

      if (false === unlink($errorOutputPath))
      {
        $output->writeln("<error>Error deleting $errorOutputPath file</error>\n");
        return Command::FAILURE;
      }
    }
    else
    {
      $output->writeln("<comment>Database $name already exists.</comment>\n");
      exit(Command::SUCCESS);
    }

    # Create the migrations table
    $migrationsTableName = self::getMigrationsTableName();
    $query = "CREATE TABLE IF NOT EXISTS $migrationsTableName (
      migration VARCHAR(255) NOT NULL PRIMARY KEY,
      ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $database = new self($name, $input, $output);

    if (false === $database->exec($query) )
    {
      $output->writeln("<error>Failed to create the migrations table.</error>\n");
      return Command::FAILURE;
    }

    $output->writeln("<info>MySQL database successfully set up.</info>\n", OutputInterface::VERBOSITY_VERBOSE);
    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function drop(): int
  {
    $result = $this->query("DROP DATABASE $this->name");

    if (false === $result)
    {
      $this->output->writeln('<error>Failed to drop the database.</error>');
      return Command::FAILURE;
    }

    $this->output->writeln('<info>Database dropped.</info>');
    return Command::SUCCESS;
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
    $query = "SHOW TABLES LIKE '$tableName'";

    $result = $this->query($query);

    if (false === $result)
    {
      $this->output->writeln("<error>Failed to check if the table exists.</error>\n");
      return false;
    }

    if (0 === $result->rowCount())
    {
      $this->output->writeln("<comment>Table $tableName does not exist.</comment>\n");
      return false;
    }

    return true;
  }

  /**
   * @inheritDoc
   */
  public function createMigrationsTable(): int
  {
    $migrationsTable = self::getMigrationsTableName();

    $query = "CREATE TABLE IF NOT EXISTS $migrationsTable (
      migration VARCHAR(255) NOT NULL PRIMARY KEY,
      ran_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $result = $this->exec($query);

    if (false === $result)
    {
      $this->output->writeln("<error>Failed to create the migrations table.</error>\n");
      return Command::FAILURE;
    }

    $this->output->writeln("<info>Migrations table created.</info>\n");
    return Command::SUCCESS;
  }
}