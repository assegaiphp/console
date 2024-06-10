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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MySQLDatabase. This class is a MySQL database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class MySQLDatabase extends PDO implements DatabaseConnectionInterface
{
  /**
   * @var Inspector
   */
  protected Inspector $inspector;

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

    $dbConfig = new DBConfig($this->input, $this->output, $this->name, DatabaseType::MYSQL->value);
    $dbConfig->load();

    $host = $dbConfig->get('host') ?? DEFAULT_MYSQL_HOST;
    $username = $dbConfig->get('username') ?? '';
    $password = $dbConfig->get('password') ?? '';
    $port = $dbConfig->get('port') ?? DEFAULT_MYSQL_PORT;

    $dsn = "mysql:host=$host;port=$port;dbname=$this->name";

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
      $inspector = new Inspector($input, $output);
      $workingDirectory = getcwd() ?: '';
      $configFilename = Path::join($workingDirectory, 'config', 'local.php');

      if (! $inspector->isValidWorkspace($workingDirectory))
      {
        $output->writeln('<error>This is not a valid workspace.</error>');
        return false;
      }

      if (! file_exists($configFilename))
      {
        $output->writeln('<error>Database configuration does not exist.</error>');
        return false;
      }

      $config = require $configFilename;

      if (! isset($config['databases'][$name]))
      {
        $output->writeln('<error>Database does not exist.</error>');
        return false;
      }

      return true;
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
   * @inheritDoc
   */
  public function setup(): int
  {
    $result = $this->query("CREATE DATABASE $this->name");

    if (false === $result)
    {
      $this->output->writeln('<error>Failed to create the database.</error>');
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
      $this->output->writeln('<error>Failed to drop the database.</error>');
      return Command::FAILURE;
    }

    $this->output->writeln('<info>Database dropped.</info>');
    return Command::SUCCESS;
  }
}