<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\AppConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Class MigrationCreate. This class is a command that creates a new migration.
 *
 * @package Assegai\Console\Commands\Migrations
 */
#[AsCommand(
    name: 'migration:create',
    description: 'Create a new migration',
    aliases: ['m:create', 'migration:make']
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
      ->addOption('database_type', 'dt', InputArgument::OPTIONAL, 'The type of the migration', DEFAULT_DATABASE_TYPE, DatabaseType::toArray())
      ->addOption('database', 'db', InputArgument::OPTIONAL, 'The name of the database')
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use MySQL database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use SQLite database');
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);
    $config = new AppConfig($input, $output);
    /** @var QuestionHelper $helper */
    $helper = $this->getHelper('question');

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

    if (Command::SUCCESS !== $config->load())
    {
      $output->writeln("<error>Failed to load configuration.</error>\n");
      return Command::FAILURE;
    }

    // Create migration subdirectory if it does not exist
    $type = $input->getOption('database_type');
    $dbName = $input->getOption('database');

    if (!DatabaseType::isValid($type)) {
      $output->writeln("<error>Invalid database type.</error>\n");
      return Command::FAILURE;
    }

    if ($input->getOption(DatabaseType::MYSQL->value)) {
      $type = DatabaseType::MYSQL->value;
    } elseif ($input->getOption(DatabaseType::POSTGRESQL->value)) {
      $type = DatabaseType::POSTGRESQL->value;
    } elseif ($input->getOption(DatabaseType::SQLITE->value)) {
      $type = DatabaseType::SQLITE->value;
    }

    if (! $dbName ) {
      $path = "databases.$type";
      $databases = array_keys($config->get($path, []));
      $question = new ChoiceQuestion("<info>?</info> Which <question>$type</question> database would you like to create the migration for? ", $databases, 0);
      $dbName = $helper->ask($input, $output, $question);
    }

    // Get the migration name
    $migrationName = $this->getMigrationName($input->getArgument('name'));
    $path = Path::join($migrationDirectory, $type, $dbName, $migrationName);

    if (!file_exists($path)) {
      if (! mkdir($path, recursive: true)) {
        $output->writeln("<error>Failed to create migration directory.</error>\n");
        return Command::FAILURE;
      }
    }

    // Create the migration files
    $upFileBytes = file_put_contents(Path::join($path, 'up.sql'), "");
    $downFileBytes = file_put_contents(Path::join($path, 'down.sql'), "");
    if (false === $upFileBytes || false === $downFileBytes) {
      $output->writeln("<error>Failed to create migration files.</error>\n");
      return Command::FAILURE;
    }

    $relativePath = Path::join('migrations', str_replace($migrationDirectory, '', $path));

    // Output success message
    $output->writeln([
      "<info>CREATE</info> $relativePath/up.sql ($upFileBytes bytes)",
      "<info>CREATE</info> $relativePath/down.sql ($downFileBytes bytes)\n",
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