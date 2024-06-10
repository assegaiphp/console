<?php

namespace Assegai\Console\Core\Database;

use Assegai\Console\Core\Database\Interfaces\DatabaseConnectionInterface;
use Assegai\Console\Tests\Mocks\MockInput;
use Assegai\Console\Tests\Mocks\MockOutput;
use PDO;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SQLiteDatabase. This class is a SQLite database connection.
 *
 * @package Assegai\Console\Core\Database
 */
class SQLiteDatabase extends PDO implements DatabaseConnectionInterface
{

  /**
   * @param mixed $name
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(mixed $name, InputInterface $input, OutputInterface $output)
  {
  }

  public static function exists(string $name): bool
  {
    $input = new MockInput();
    $output = new MockOutput();

    $db = new SQLiteDatabase($name, $input, $output);
  }

  public static function doesNotExist(string $name): bool
  {
    return ! self::exists($name);
  }

  public function setup(): int
  {

  }

  public function drop(): int
  {
    // TODO: Implement drop() method.
  }
}