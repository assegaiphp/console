<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MigrationCreate. This class is a command that creates a new migration.
 *
 * @package Assegai\Console\Commands\Migration
 */
#[AsCommand(
    name: 'migration:create',
    description: 'Create a new migration',
    aliases: ['migrate:create']
)]
class MigrationCreate extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this
      ->setHelp('This command creates a new migration file in the migrations directory.')
      ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration')
      ->addOption('type', 't', InputArgument::OPTIONAL, 'The type of the migration', DatabaseType::MYSQL->value);
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement the create logic
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace(getcwd() ?: '')) {
      $output->writeln("<error>Invalid workspace.</error>\n");
      return Command::FAILURE;
    }

    // Check if migrations directory exists
    $migrationDirectory = Path::getMigrationsDirectory();

    if (!file_exists($migrationDirectory)) {
      $output->writeln("<error>Migrations directory does not exist.</error>\n");
      $output->writeln("<fg=gray>Run 'assegai migration:setup' to create the migrations directory.</>\n");
      return Command::FAILURE;
    }

    // Create migration subdirectory if it does not exist
    $type = $input->getOption('type');

    // Get the migration name
    $migrationName = $this->getMigrationName($input->getArgument('name'));
    $path = Path::join($migrationDirectory, $type, $migrationName);

    if (!file_exists($path))
    {
      if (! mkdir($path, recursive: true))
      {
        $output->writeln("<error>Failed to create migration directory.</error>\n");
        return Command::FAILURE;
      }
    }

    // Create the migration files
    if (false === file_put_contents(Path::join($path, 'up.sql'), "-- up.sql") ||
        false === file_put_contents(Path::join($path, 'down.sql'), "-- down.sql"))
    {
      $output->writeln("<error>Failed to create migration files.</error>\n");
      return Command::FAILURE;
    }

    // Output success message
    $output->writeln([
      "<info>CREATE</info> migrations/$type/$migrationName/up.sql",
      "<info>CREATE</info> migrations/$type/$migrationName/down.sql\n",
    ]);

    return Command::SUCCESS;
  }

  /**
   * Get the migration name
   *
   * @param string $name
   * @return string
   */
  public function getMigrationName(string $name): string
  {
    $timestamp = date('YmdHis');
    $name = new Text($name);
    return $timestamp . '_' . $name->snakeCase();
  }
}