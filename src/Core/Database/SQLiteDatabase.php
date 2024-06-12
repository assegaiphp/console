<?php

namespace Assegai\Console\Core\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
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

      $helper = new QuestionHelper();
      $confirmQuestion = new ConfirmationQuestion("<info>?</info> Do you want to create the database? <fg=gray>(Y/n)</>", true);

      if (! $helper->ask($input, $output, $confirmQuestion) )
      {
        return false;
      }
    }

    return file_exists($path);
  }

  public static function doesNotExist(string $name): bool
  {
    return ! self::exists($name);
  }

  /**
   * @inheritDoc
   */
  public function setup(): int
  {
    return file_exists($this->path) ? Command::SUCCESS : Command::FAILURE;
  }

  /**
   * @inheritDoc
   */
  public function drop(): int
  {
    return unlink($this->path) ? Command::SUCCESS : Command::FAILURE;
  }
}