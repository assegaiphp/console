<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Core\Database\SQLiteDatabase;
use Assegai\Console\Exceptions\AssegaiConsoleException;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Enumerations\ParameterKey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'database:load',
    description: 'Load a schema.sql file to the database',
    aliases: ['db:load']
)]
class DatabaseLoad extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument(ParameterKey::DB_NAME->value, InputArgument::REQUIRED, 'The name of the database')
      ->addArgument('file', InputArgument::REQUIRED, 'The path to the schema.sql file')
      ->addOption(ParameterKey::DB_TYPE->value, ParameterKey::DB_TYPE->getShortName(), InputArgument::OPTIONAL, 'The type of the database', DatabaseType::MYSQL->value, DatabaseType::toArray())
      ->addOption(DatabaseType::MYSQL->value, null, InputOption::VALUE_NONE, 'Use MySQL database')
      ->addOption(DatabaseType::MARIADB->value, null, InputOption::VALUE_NONE, 'Use MariaDB database')
      ->addOption(DatabaseType::POSTGRESQL->value, null, InputOption::VALUE_NONE, 'Use PostgreSQL database')
      ->addOption(DatabaseType::SQLITE->value, null, InputOption::VALUE_NONE, 'Use SQLite database')
      ->addOption(DatabaseType::MSSQL->value, null, InputOption::VALUE_NONE, 'Use MSSQL database');
  }

  /**
   * @throws AssegaiConsoleException
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $database = (string) $input->getArgument(ParameterKey::DB_NAME->value);
    $file = (string) $input->getArgument('file');
    $type = get_datasource_type($input, $output) ?: throw new AssegaiConsoleException('Database type is not specified. Use the --db-type option to specify the database type.');

    if (! file_exists($file)) {
      $output->writeln('<error>File not found</error>');
      return Command::FAILURE;
    }

    if (! DatabaseType::isValid($type)) {
      $output->writeln('<error>Invalid database type</error>');
      return Command::FAILURE;
    }

    $config = new DBConfig($input, $output, $database, $type);
    if (Command::SUCCESS !== $config->load()) {
      $output->writeln('<error>Failed to load the database configuration</error>');
      return Command::FAILURE;
    }

    $configPath = "$type.$database";
    $output->writeln('');

    $command = match ($type) {
      DatabaseType::MYSQL->value => $this->buildMySqlLoadCommand(
        'mysql',
        (string) $config->get("$configPath.host", DEFAULT_MYSQL_HOST),
        (int) $config->get("$configPath.port", DEFAULT_MYSQL_PORT),
        (string) ($config->get("$configPath.username") ?? $config->get("$configPath.user", DEFAULT_MYSQL_USER)),
        (string) ($config->get("$configPath.password") ?? ''),
        $database,
        $file,
      ),
      DatabaseType::MARIADB->value => $this->buildMySqlLoadCommand(
        is_installed('mariadb') ? 'mariadb' : 'mysql',
        (string) $config->get("$configPath.host", DEFAULT_MARIADB_HOST),
        (int) $config->get("$configPath.port", DEFAULT_MARIADB_PORT),
        (string) ($config->get("$configPath.username") ?? $config->get("$configPath.user", DEFAULT_MARIADB_USER)),
        (string) ($config->get("$configPath.password") ?? ''),
        $database,
        $file,
      ),
      DatabaseType::POSTGRESQL->value => $this->buildPostgreSqlLoadCommand(
        (string) $config->get("$configPath.host", DEFAULT_POSTGRES_HOST),
        (int) $config->get("$configPath.port", DEFAULT_POSTGRES_PORT),
        (string) ($config->get("$configPath.username") ?? $config->get("$configPath.user", DEFAULT_POSTGRES_USER)),
        (string) ($config->get("$configPath.password") ?? ''),
        $database,
        $file,
      ),
      DatabaseType::SQLITE->value => $this->buildSqliteLoadCommand(
        SQLiteDatabase::normalizePath((string) $config->get("$configPath.path", DEFAULT_SQLITE_PATH)),
        $file,
      ),
      DatabaseType::MSSQL->value => $this->buildMsSqlLoadCommand(
        (string) $config->get("$configPath.host", DEFAULT_MSSQL_HOST),
        (int) $config->get("$configPath.port", DEFAULT_MSSQL_PORT),
        (string) ($config->get("$configPath.username") ?? $config->get("$configPath.user", DEFAULT_MSSQL_USER)),
        (string) ($config->get("$configPath.password") ?? ''),
        $database,
        $file,
      ),
      default => null,
    };

    if ($command === null || false === shell_exec($command)) {
      $output->writeln('<error>Failed to load the schema.sql file</error>');
      return Command::FAILURE;
    }

    $output->writeln('<info>Schema.sql file loaded successfully</info>');
    return Command::SUCCESS;
  }

  protected function buildMySqlLoadCommand(
    string $binary,
    string $host,
    int $port,
    string $user,
    string $password,
    string $database,
    string $file,
  ): string {
    return $binary . ' ' .
      '-u ' . escapeshellarg($user) . ' ' .
      '-h ' . escapeshellarg($host) . ' ' .
      '-P ' . escapeshellarg((string) $port) . ' ' .
      '-p' . escapeshellarg($password) . ' ' .
      escapeshellarg($database) . ' ' .
      '< ' . escapeshellarg($file);
  }

  protected function buildPostgreSqlLoadCommand(
    string $host,
    int $port,
    string $user,
    string $password,
    string $database,
    string $file,
  ): string {
    return 'PGPASSWORD=' . escapeshellarg($password) . ' ' .
      'psql ' .
      '-U ' . escapeshellarg($user) . ' ' .
      '-h ' . escapeshellarg($host) . ' ' .
      '-p ' . escapeshellarg((string) $port) . ' ' .
      escapeshellarg($database) . ' ' .
      '< ' . escapeshellarg($file);
  }

  protected function buildSqliteLoadCommand(string $path, string $file): string
  {
    return 'sqlite3 ' . escapeshellarg($path) . ' < ' . escapeshellarg($file);
  }

  protected function buildMsSqlLoadCommand(
    string $host,
    int $port,
    string $user,
    string $password,
    string $database,
    string $file,
  ): string {
    return 'sqlcmd ' .
      '-S ' . escapeshellarg($host . ',' . $port) . ' ' .
      '-U ' . escapeshellarg($user) . ' ' .
      '-P ' . escapeshellarg($password) . ' ' .
      '-C ' .
      '-d ' . escapeshellarg($database) . ' ' .
      '-i ' . escapeshellarg($file);
  }
}
