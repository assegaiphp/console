<?php

namespace Assegai\Console\Core\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Exception;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class SQLiteDatabase. This class is a SQLite database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class SQLiteDatabase extends PDO implements DatabaseConnectionInterface
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

    if (! $inspector->isValidWorkspace(getcwd() ?: '') )
    {
      $this->output->writeln('<error>Failed to load MySQL config. Invalid workspace.</error>');
      exit(Command::FAILURE);
    }

    $dbConfig = new DBConfig($this->input, $this->output, $this->name, DatabaseType::MYSQL->value);
    if (Command::SUCCESS !== $dbConfig->load())
    {
      $this->output->writeln('<error>Database configuration not found.</error>');
      exit(Command::FAILURE);
    }

    $this->path = Path::join(Path::getWorkingDirectory() ?: '', $dbConfig->get("sqlite.$this->name.path") ?? exit(Command::FAILURE));
    $dataDirectory = Path::join(Path::getWorkingDirectory() ?: '', '.data');
    if (! is_dir($dataDirectory) )
    {
      if (false === mkdir($dataDirectory))
      {
        $this->output->writeln('<error>Failed to create data directory.</error>');
        exit(Command::FAILURE);
      }
    }

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
    if (Command::SUCCESS !== $dbConfig->load())
    {
      $output->writeln('<error>Database configuration not found.</error>');
      return false;
    }

    $path = $dbConfig->get("sqlite.$name.path");

    if (!$path)
    {
      $output->writeln('<error>Database path not defined.</error>');
      return false;
    }

    $path = Path::join(Path::getWorkingDirectory() ?: '', $path);
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
  public static function setup(?string $name = null): int
  {
    $input = new MockInput();
    $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
    $type = DatabaseType::SQLITE->value;
    $dbConfig = new DBConfig($input, $output, $name, $type);

    if (Command::SUCCESS !== $dbConfig->load())
    {
      $output->writeln("<error>Failed to load database configuration.</error>\n");
      return Command::FAILURE;
    }

    $path = $dbConfig->get("sqlite.$name.path");

    if (!$path)
    {
      $output->writeln("<error>Database path not defined.</error>\n");
      return Command::FAILURE;
    }

    $path = Path::join(Path::getWorkingDirectory() ?: '', $path);

    if (! file_exists($path) )
    {
      $helper = new QuestionHelper();
      $confirmQuestion = new ConfirmationQuestion("<info>?</info> Do you want to create the database? <fg=gray>(Y/n)</>", true);

      if (! $helper->ask($input, $output, $confirmQuestion) )
      {
        return Command::FAILURE;
      }
    }

    $migrationsTableName = self::getMigrationsTableName();
    $query = "CREATE TABLE $migrationsTableName (migration TEXT PRIMARY KEY, ran_at TEXT)";

    try
    {
      $database = new self($name, $input, $output);

      if (false === $database->exec($query))
      {
        $output->writeln("<error>Failed to create the migrations table.</error>\n");
        return Command::FAILURE;
      }
    }
    catch (Exception $exception)
    {
      if (! str_contains($exception->getMessage(), 'already exists'))
      {
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
}