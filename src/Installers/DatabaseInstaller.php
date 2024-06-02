<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Installers\AbstractInstaller;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class DatabaseInstaller extends AbstractInstaller
{
  protected array $requiredExtensions = [
    'mysql' => ['pdo_mysql', 'mysqli'],
    'postgresql' => ['pdo_pgsql'],
    'sqlite' => ['pdo_sqlite']
  ];

  protected array $supportedDatabase = [
    'mysql',
    'postgresql',
    'sqlite'
  ];

  /**
   * @inheritDoc
   */
  public function install(): int
  {
    if (! $this->questionHelper->ask($this->input, $this->output, new ConfirmationQuestion('<info>?</info> Do you want to install the database? (Y/n) ')))
    {
      $this->output->writeln('Skipping database installation...');
      return Command::SUCCESS;
    }

    $projectPath = Path::getProjectRootPath();

    $this->output->writeln('');
    $this->output->writeln(
      $this->formatter->formatBlock("Installing the database...", 'question', true)
    );
    $this->output->writeln('');

    // Ask what database to use.
    $databaseChoiceQuestion = new ChoiceQuestion(
      '<info>?</info> Which database do you want to use? (comma separated): ',
      $this->supportedDatabase,
      0
    );
    $databaseChoiceQuestion->setMultiselect(true);
    $databaseChoices = $this->questionHelper->ask($this->input, $this->output, $databaseChoiceQuestion);

    foreach ($databaseChoices as $database)
    {
      $this->output->writeln('');
      $this->output->writeln($this->formatter->formatBlock("Configuring $database database...", 'question'));
      $this->output->writeln('');

      if ($missingExtensions = $this->checkForMissingExtensions($this->requiredExtensions[$database]))
      {
        $this->output->writeln($this->formatter->formatBlock('The following extensions are missing: ' . implode(', ', $missingExtensions), 'error', true));
        return Command::FAILURE;
      }

      $installer = match ($database) {
        'mysql' => new MySQLInstaller(
          $this->input,
          $this->output,
          $this->formatter,
          $this->questionHelper,
          $this->projectPath
        ),
        'postgresql' => new PostgreSQLInstaller(
          $this->input,
          $this->output,
          $this->formatter,
          $this->questionHelper,
          $this->projectPath
        ),
        default => new SQLiteInstaller(
          $this->input,
          $this->output,
          $this->formatter,
          $this->questionHelper,
          $this->projectPath
        ),
      };

      if (($statusCode = $installer->install()) > 0)
      {
        $this->output->writeln($this->formatter->formatBlock("Failed to install $database", 'error', true));
        return $statusCode;
      }
    }


    if (! file_exists( Path::join($projectPath, 'src', 'Users') ) )
    {
      $userResourceQuestion = new Question("<info>?</info> What is the name of the users resource? (Users) ", 'Users');
      $userServiceName = $this->questionHelper->ask($this->input, $this->output, $userResourceQuestion);
      $command = "cd $projectPath && assegai generate resource $userServiceName";

      if ( false === passthru($command) )
      {
        $this->output->writeln("<error>Failed to create resource, $userServiceName</error>");
        return Command::FAILURE;
      }
    }

    $this->output->writeln("✔️  Database installation complete\n");
    return Command::SUCCESS;
  }

  /**
   * Check for missing extensions.
   *
   * @param string[] $extensions The extensions to check for.
   *
   * @return string[] The missing extensions.
   */
  private function checkForMissingExtensions(array $extensions): array
  {
    $missingExtensions = [];

    foreach ($extensions as $extension)
    {
      if (! extension_loaded($extension) )
      {
        $missingExtensions[] = $extension;
      }
    }

    return $missingExtensions;
  }
}