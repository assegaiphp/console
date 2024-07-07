<?php

namespace Assegai\Console\Core\Database\Traits;

use Assegai\Console\Util\Config\AppConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Database name validator trait. This trait provides a method to validate the database name.
 *
 * @package Assegai\Console\Core\Database\Traits
 */
trait DatabaseNameValidatorTrait
{
  /**
   * Check if the given database name is valid.
   *
   * @param string $dbName The name of the database.
   * @param string $type The type of the database.
   * @param InputInterface $input The input interface.
   * @param OutputInterface $output The output interface.
   * @return bool True if the database name is valid, false otherwise.
   */
  private function isValidDbName(string $dbName, string $type, InputInterface $input, OutputInterface $output): bool
  {
    # Check if the database name is empty
    if (empty($dbName))
    {
      $output->writeln("<error>The database name is empty</error>\n", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    # Check if the database name is a valid identifier
    if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $dbName))
    {
      $output->writeln("<error>Invalid database name</error>\n", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    # Check if the database name is defined in the configuration file
    $appConfig = new AppConfig($input, $output);
    if (Command::SUCCESS !== $appConfig->load())
    {
      $output->writeln("<error>Failed to load the configuration file</error>\n", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    if (! $appConfig->has("databases.$type.$dbName") )
    {
      $output->writeln("<error>Database $dbName is not defined in the configuration file</error>\n", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    return true;
  }
}