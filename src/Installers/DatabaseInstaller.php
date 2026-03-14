<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use function Laravel\Prompts\multiselect;

/**
 * Class DatabaseInstaller. Installs the database.
 *
 * @package
 */
class DatabaseInstaller extends AbstractInstaller
{
  /**
   * @var array<string, string[]> $requiredExtensions The required extensions for each database.
   */
  protected array $requiredExtensions = [
    'mysql'       => ['intl', 'pdo_mysql', 'mysqli'],
    'postgresql'  => ['intl', 'pdo_pgsql'],
    'sqlite'      => ['intl', 'pdo_sqlite']
  ];

  /**
   * @var array<string> $supportedDatabase The supported databases.
   */
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
    if (! $this->questionHelper->ask($this->input, $this->output, new ConfirmationQuestion('<info>?</info> Would you like to add a database configuration? <fg=gray>(Y/n)</> ')))
    {
      $this->output->writeln('');
      $this->output->writeln('<comment>Skipping database configuration...</comment>');
      $this->output->writeln('');
      return Command::SUCCESS;
    }

    $this->output->writeln('');
    $this->output->writeln(
      $this->formatter->formatBlock("Configuring databases...", 'question', true)
    );
    $this->output->writeln('');

    // Ask what database to use.
    $databaseChoices = multiselect('<info>?</info> Which database do you want to use? <fg=gray>(comma separated)</> ', $this->supportedDatabase, [$this->supportedDatabase[0]]);

    foreach ($databaseChoices as $database)
    {
      $this->output->writeln('');
      $this->output->writeln($this->formatter->formatBlock("Configuring $database database...", 'question', true));
      $this->output->writeln('');

      if ($missingExtensions = $this->checkForMissingExtensions($this->requiredExtensions[$database]))
      {
        $this->output->writeln($this->formatter->formatBlock('The following extensions are missing: ' . implode(', ', $missingExtensions), 'error', true));
        return Command::FAILURE;
      }

      $dbInstaller = match ($database) {
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

      if (($statusCode = $dbInstaller->install()) > 0)
      {
        $this->output->writeln($this->formatter->formatBlock("Failed to install $database", 'error', true));
        return $statusCode;
      }
    }


    if (! file_exists( Path::join($this->projectPath, 'src', 'Users') ) )
    {
      $userResourceQuestion = new Question("<info>?</info> What is the name of the users' resource? <fg=gray>(Users)</> ", 'Users');
      $userServiceName = $this->questionHelper->ask($this->input, $this->output, $userResourceQuestion);
      $command = $this->buildGenerateResourceCommand((string)$userServiceName);
      $statusCode = $this->runCommand($command);

      if ($statusCode !== Command::SUCCESS)
      {
        $this->output->writeln([
          '',
          "<error>Failed to create resource, $userServiceName</error>",
          ''
        ]);
        return Command::FAILURE;
      }
    }

    $ormInstallationCommand = shell_exec(
      sprintf('cd %s && composer --ansi require assegaiphp/orm', escapeshellarg($this->projectPath))
    );

    if (false === $ormInstallationCommand)
    {
      $this->output->writeln([
        '',
        '<error>Failed to install ORM</error>',
        ''
      ]);
      return Command::FAILURE;
    }

    $this->output->writeln([
      '',
      "✔️  Database installation complete\n",
      ''
    ]);

    return Command::SUCCESS;
  }

  /**
   * Build the command used to generate the default users resource.
   */
  protected function buildGenerateResourceCommand(string $resourceName): string
  {
    $consoleBinary = $this->resolveConsoleBinaryCommand();

    return sprintf(
      'cd %s && %s --ansi generate resource %s',
      escapeshellarg($this->projectPath),
      $consoleBinary,
      escapeshellarg($resourceName)
    );
  }

  /**
   * Resolve the currently installed console binary so subprocesses use the same code path.
   */
  protected function resolveConsoleBinaryCommand(): string
  {
    $consoleBinaryPath = realpath(Path::join(dirname(__DIR__, 2), 'bin', 'assegai'));

    if (false === $consoleBinaryPath) {
      return 'assegai';
    }

    return sprintf('%s %s', escapeshellarg(PHP_BINARY), escapeshellarg($consoleBinaryPath));
  }

  /**
   * Run a shell command and return its exit status.
   */
  protected function runCommand(string $command): int
  {
    passthru($command, $statusCode);

    return $statusCode;
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
