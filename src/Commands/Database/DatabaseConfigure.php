<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DatabaseConfig. This class is a command that sets up the database configuration.
 *
 * @package Assegai\Console\Commands\Database
 */
#[AsCommand(
  name: 'database:configure',
  description: 'Setup the database configuration',
  aliases: ['database:config', 'db:config']
)]
class DatabaseConfigure extends Command
{
  /**
   * @var string[] $validTypes The valid types of the database.
   */
  protected array $validTypes = ['mysql', 'pgsql', 'sqlite'];

  public function configure(): void
  {
    $this->setHelp('This command sets up the connection configuration for a database. It creates a configuration file in the config directory.');
    $this
      ->addArgument('name', InputArgument::REQUIRED, 'The name of the database')
      ->addOption(
        'type',
        't',
        InputArgument::OPTIONAL,
        'The type of the database',
        'mysql',
        $this->validTypes
      );

    $this->addOption(
      'host',
      'H',
      InputArgument::OPTIONAL,
      'The host of the database',
      'localhost'
    );

    $this->addOption(
      'port',
      'P',
      InputArgument::OPTIONAL,
      'The port of the database',
      '3306'
    );

    $this->addOption(
      'user',
      'u',
      InputArgument::OPTIONAL,
      'The user of the database',
      'root',
    );

    $this->addOption(
      'password',
      'p',
      InputArgument::OPTIONAL,
      'The password of the database',
      '',
    );


  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);
    $workingDirectory = getcwd() ?: '';
    $configFilename = Path::join($workingDirectory, 'config', 'local.php');

    if (! $inspector->isValidWorkspace($workingDirectory))
    {
      $output->writeln('<error>This is not a valid workspace.</error>');
      return Command::FAILURE;
    }

    if (! file_exists($configFilename))
    {
      $configFilename = Path::join($workingDirectory, 'config', 'default.php');

      if (! file_exists($configFilename))
      {
        $output->writeln('<error>The configuration file does not exist.</error>');
        return Command::FAILURE;
      }
    }

    $questionHelper = $this->getHelper('question');

    if ($input->getOption('type') !== 'sqlite')
    {
      $output->writeln('Configuring the SQLite database...');
      return Command::SUCCESS;
    }

    $host = $input->getOption('host');
    $port = $input->getOption('port');
    $user = $input->getOption('user');
    $name = $input->getArgument('name');

    $output->writeln('Configuring the database <info>$name</info>...');
    return Command::SUCCESS;
  }
}