<?php

namespace Assegai\Console\Commands\Database;

use Assegai\Console\Core\Database\Enumerations\DatabaseType;
use Assegai\Console\Util\Config\DBConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

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
      'The host of the database'
    );

    $this->addOption(
      'port',
      'P',
      InputArgument::OPTIONAL,
      'The port of the database'
    );

    $this->addOption(
      'user',
      'u',
      InputArgument::OPTIONAL,
      'The user of the database',
    );

    $this->addOption(
      'password',
      'p',
      InputArgument::OPTIONAL,
      'The password of the database',
    );
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);
    $workingDirectory = getcwd() ?: '';
    $configFilename = Path::join($workingDirectory, 'config', 'local.php');
    $type = $input->getOption('type');

    if (! $inspector->isValidWorkspace($workingDirectory))
    {
      $output->writeln([
        '',
        '<error>This is not a valid workspace.</error>',
        ''
      ]);
      return Command::FAILURE;
    }

    if (! file_exists($configFilename))
    {
      $configFilename = Path::join($workingDirectory, 'config', 'default.php');

      if (! file_exists($configFilename))
      {
        $output->writeln([
          '',
          '<error>The configuration file does not exist.</error>',
          ''
        ]);
        return Command::FAILURE;
      }
    }

    /** @var QuestionHelper $questionHelper */
    $questionHelper = $this->getHelper('question');

    if (! DatabaseType::isValid($type) )
    {
      $output->writeln([
        '',
        '<error>Invalid database type.</error>',
        ''
      ]);
      $type = $questionHelper->ask($input, $output, new ChoiceQuestion('<info>?</info> Database type: ', DatabaseType::toArray(), 0));
      $output->writeln([
        "Selected database type: <info>$type</info>",
        ''
      ]);
    }

    $name = $input->getArgument('name');

    if ($type === 'sqlite')
    {
      $output->writeln([
        '',
        'Configuring the SQLite database...',
        ''
      ]);

      $dsn = 'sqlite:';
      $choices = ['on-disk', 'in-memory', 'in-memory (persistent)'];

      $typeQuestion =
        new ChoiceQuestion("<info>?</info> How do you want to store your data?: ", $choices, 0);
      $sqlType = $questionHelper->ask($input, $output, $typeQuestion);

      $dsn .= match ($sqlType) {
        'on-disk' => ".data/$name.sq3",
        'in-memory' => ':memory:',
        'in-memory (persistent)' => 'file::memory:?cache=shared'
      };

      $dbConfig = new DBConfig($input, $output, $name, $type);
      if (Command::SUCCESS !== $dbConfig->load())
      {
        $output->writeln([
          '',
          '<error>Failed to load database configuration.</error>',
          ''
        ]);
        return Command::FAILURE;
      }
      $dbConfig->set("$type.$name", ['path' => str_replace('sqlite:', '', $dsn)]);

      if (Command::SUCCESS !== $dbConfig->commit())
      {
        $output->writeln('<error>Failed to save database configuration.</error>');
        return Command::FAILURE;
      }

      return Command::SUCCESS;
    }

    $host = $input->getOption('host');
    $port = $input->getOption('port');
    $user = $input->getOption('user');

    $output->writeln("Configuring the database <info>$name</info>...");

    if (! $host )
    {
      $defaultHost = match ($type) {
        'mysql' => DEFAULT_MYSQL_HOST,
        'pgsql' => DEFAULT_POSTGRES_HOST,
        default => ''
      };
      $host = $questionHelper->ask($input, $output, new Question("<info>?</info> Host: (<fg=gray>$defaultHost</>) ", $defaultHost));
    }

    if (! $port )
    {
      $defaultPort = match ($type) {
        'mysql' => DEFAULT_MYSQL_PORT,
        'pgsql' => DEFAULT_POSTGRES_PORT,
        default => ''
      };
      $port = $questionHelper->ask($input, $output, new Question("<info>?</info> Port: (<fg=gray>$defaultPort</>) ", $defaultPort));
    }

    if (! $user )
    {
      $defaultUser = match ($type) {
        'mysql' => DEFAULT_MYSQL_USER,
        'pgsql' => DEFAULT_POSTGRES_USER,
        default => ''
      };
      $user = $questionHelper->ask($input, $output, new Question("<info>?</info> User: (<fg=gray>$defaultUser</>) ", $defaultUser));
    }

    $passwordQuestion = new Question('<info>?</info> Password: ', '');
    $passwordQuestion->setHidden(true);
    $password = $questionHelper->ask($input, $output, $passwordQuestion);

    $dbConfig = new DBConfig($input, $output, $name, $type);
    if (Command::SUCCESS !== $dbConfig->load())
    {
      $output->writeln([
        '',
        '<error>Failed to load database configuration.</error>',
        ''
      ]);
      return Command::FAILURE;
    }

    $dbConfig->set("$type.$name", [
      'host' => $host,
      'port' => (int)$port,
      'user' => $user,
      'password' => $password
    ]);

    $output->writeln('');
    if ($dbConfig->commit() !== Command::SUCCESS)
    {
      $output->writeln('<error>Failed to save database configuration.</error>');
      return Command::FAILURE;
    }
    return Command::SUCCESS;
  }
}