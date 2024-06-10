<?php

namespace Assegai\Console\Core\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Inspector;
use Exception;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MySQLDatabase. This class is a MySQL database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class PostgreSQLDatabase extends PDO implements DatabaseConnectionInterface
{
  /**
   * @var Inspector
   */
  protected Inspector $inspector;

  /**
   * PostgreSQLDatabase constructor.
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

    // Check if the workspace is valid
    if (! $this->inspector->isValidWorkspace(getcwd() ?: '') )
    {
      $this->output->writeln('<error>Failed to load PostgreSQL config. Invalid workspace.</error>');
      exit(Command::FAILURE);
    }

    // Check if the database configuration exists
    $dbConfig = new DBConfig($this->input, $this->output, $this->name, DatabaseType::POSTGRESQL->value);
    $dbConfig->load();

    // Create the DSN
    $dsn = "pgsql:host=localhost;port=5432;dbname=$this->name";
    $username = $dbConfig->get('username') ?? '';
    $password = $dbConfig->get('password') ?? '';

    // Construct the parent class
    parent::__construct($dsn, $username, $password);
  }

  /**
   * @inheritDoc
   */
  public static function exists(string $name): bool
  {
    $input = new MockInput();
    $output = new MockOutput();

    try
    {
      new self($name, $input, $output);
      return true;
    }
    catch (Exception)
    {
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
   * @inheritDoc
   */
  public function setup(): int
  {
    $result = $this->query("CREATE DATABASE $this->name");

    if (false === $result)
    {
      return Command::FAILURE;
    }

    $this->output->writeln('<info>Database created.</info>');
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
      return Command::FAILURE;
    }

    $this->output->writeln('<info>Database dropped.</info>');
    return Command::SUCCESS;
  }
}