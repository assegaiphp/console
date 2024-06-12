<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\DBConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
      ->addArgument('database', InputArgument::REQUIRED, 'The name of the database')
      ->addArgument('file', InputArgument::REQUIRED, 'The path to the schema.sql file')
      ->addOption('type', 't', InputArgument::OPTIONAL, 'The type of the database', 'mysql');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $database = $input->getArgument('database');
    $file = $input->getArgument('file');
    $type = $input->getOption('type');

    if (! file_exists($file))
    {
      $output->writeln('<error>File not found</error>');
      return Command::FAILURE;
    }

    if (! DatabaseType::isValid($input->getOption('type') ?? ''))
    {
      $output->writeln('<error>Invalid database type</error>');
      return Command::FAILURE;
    }

    $config = new DBConfig($input, $output, $database, $type);

    $output->writeln('');
    switch ($type)
    {
      case DatabaseType::MYSQL->value:
        $host = $config->get('host', DEFAULT_MYSQL_HOST);
        $port = $config->get('port', DEFAULT_MYSQL_PORT);
        $user = $config->get('user', DEFAULT_MYSQL_USER);
        $password = $config->get('password', '');

        if (false === `mysql -u $user -h $host -P $port -p$password $database < $file`)
        {
          $output->writeln('<error>Failed to load the schema.sql file</error>');
          return Command::FAILURE;
        }
        break;
      case DatabaseType::POSTGRESQL->value:
        $host = $config->get('host', DEFAULT_POSTGRES_HOST);
        $port = $config->get('port', DEFAULT_POSTGRES_PORT);
        $user = $config->get('user', DEFAULT_POSTGRES_USER);
        $password = $config->get('password', '');

        if (false === "PGPASSWORD=$password psql -U $user -h $host -p $port $database < $file")
        {

        }
        break;
      case DatabaseType::SQLITE->value:
        $command = "sqlite3 $database < $file";
        break;
    }

    $output->writeln('<info>Schema.sql file loaded successfully</info>');
    return Command::SUCCESS;
  }
}