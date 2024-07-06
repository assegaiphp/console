<?php

namespace Assegai\Console\Core;

use Assegai\Console\Installers\ComposerDependencyInstaller;
use Assegai\Console\Installers\DatabaseInstaller;
use Assegai\Console\Util\Enumerations\Color;
use Assegai\Console\Util\Enumerations\ColorFX;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class WorkspaceManager. Manages the workspace.
 *
 * @package Assegai\Console\Core
 */
class WorkspaceManager
{
  protected ?string $projectPath = null;

  /**
   * WorkspaceManager constructor.
   *
   * @param InputInterface $input The input interface.
   * @param OutputInterface $output The output interface.
   * @param FormatterHelper $formatter The formatter helper.
   * @param QuestionHelper $questionHelper The question helper.
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected FormatterHelper $formatter,
    protected QuestionHelper $questionHelper
  )
  {
  }

  /**
   * Initializes the project
   *
   * @param string|null $projectName The project name
   * @param string|null $workingDirectory The working directory
   * @return int The command status
   */
  public function init(?string &$projectName = null, ?string $workingDirectory = null): int
  {
    $workingDirectory = $workingDirectory ?? getcwd();
    $templatePath = Path::getTemplatesDirectory();
    $defaultProjectName = DEFAULT_PROJECT_NAME;

    $this->output->writeln($this->formatter->formatBlock("Initializing the project...", 'question', true));
    $this->output->writeln('');

    if (! $projectName )
    {
      $projectNameQuestion = new Question("<info>?</info> Project name: <fg=gray>($defaultProjectName)</> ", $defaultProjectName);
      $projectName = $this->questionHelper->ask($this->input, $this->output, $projectNameQuestion);
    }
    $projectNameText = new Text($projectName);
    $projectDirectory = Path::join($workingDirectory ?: '', $projectNameText->kebabCase());

    if ( file_exists($projectDirectory) )
    {
      $this->output->writeln("<error>Project directory already exists: $projectDirectory</error>");
      return Command::FAILURE;
    }

    if (! mkdir($projectDirectory, 0777, true) )
    {
      $this->output->writeln("<error>\nFailed to create project directory: $projectDirectory</error>");
      return Command::FAILURE;
    }

    if (! copy_directory($templatePath, $projectDirectory) )
    {
      $this->output->writeln("<error>\nFailed to copy project template</error>");
      return Command::FAILURE;
    }

    $description = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Description: ")) ?? "";
    $defaultVersion = DEFAULT_PROJECT_VERSION;
    $version = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Version: <fg=gray>($defaultVersion)</> ", $defaultVersion));
    $version = $this->filterVersion($version);
    $type = 'project';

    $assegaiConfig = [
      "name" => $projectName,
      "description" => $description ,
      "version" => $version,
      "projectType" => $type,
      "root" => "",
      "sourceRoot" => "src",
      "scripts" => [
        "test" => "vendor/bin/pest tests",
      ],
      "development" => [
        "server" => [
          "host" => "localhost",
          "port" => 5000,
          "openBrowser" => true
        ]
      ]
    ];
    $targetAssegaiConfigPath = Path::join($projectDirectory, 'assegai.json');

    if (! file_put_contents($targetAssegaiConfigPath, json_encode($assegaiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ) {
      $this->output->writeln("<error>\nFailed to create assegai.json file</error>");
      return Command::FAILURE;
    }

    $projectNameText = new Text($projectName);
    $defaultPackageName = 'assegaiphp/' . $projectNameText->snakeCase();
    $packageName = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Package name: <fg=gray>($defaultPackageName)</> ", $defaultPackageName));
    [$vendor, $package] = explode('/', $packageName);
    $defaultNamespace = Text::snakeCaseToPascalCase($vendor) . '\\' . Text::snakeCaseToPascalCase($package) . '\\';
    $namespace = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Namespace: <fg=gray>($defaultNamespace)</> ", $defaultNamespace));

    if (! str_ends_with($namespace, '\\') ) {
      $namespace .= '\\';
    }

    $composerConfig = [
      "name" => $packageName,
      "description" => $description ,
      "type" => $type,
      "scripts" => [
        "start" => "php -S localhost:5000 bootstrap.php",
        "test"  => "vendor/bin/pest"
      ],
      "license" => "MIT",
      "autoload" => [
        "psr-4" => [
          $namespace => "src/"
        ]
      ],
      "authors" => [],
      "require" => [
        "php" => ">=" . MIN_PHP_VERSION,
        "ext-pdo" => "*",
        "ext-curl" => "*",
        "vlucas/phpdotenv" => "^5.4",
      ]
    ];
    $targetComposerConfigPath = Path::join($projectDirectory, 'composer.json');

    if (! file_put_contents($targetComposerConfigPath, json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ) {
      $this->output->writeln("<error>\nFailed to create composer.json file</error>");
      return Command::FAILURE;
    }

    # Update namespace in project files
    if (($statusCode = $this->updateNamespace($projectDirectory, $namespace)) > 0) {
      $this->output->writeln("<error>\nFailed to update namespace in project files</error>");
      return $statusCode;
    }

    # Initialize the git repository
    $initGitQuestion = new ConfirmationQuestion("<info>?</info> Initialize git repository? <fg=gray>(y/N)</> ", false);
    if (
      is_installed('git') &&
      $this->questionHelper->ask($this->input, $this->output, $initGitQuestion)
    ) {
      if ( boolval($this->input->getOption('skip-git')) !== true ) {
        $this->output->writeln('');
        $this->output->writeln(
          $this->formatter->formatBlock('Initializing git repository...', 'question', true),
          OutputInterface::VERBOSITY_VERBOSE
        );
        $gitInit = `cd $projectDirectory && git init`;

        if (! str_contains($gitInit, 'Initialized empty Git repository') ) {
          $this->output->writeln("<error>\nFailed to initialize git repository</error>");
          return Command::FAILURE;
        }
      }
    }

    $this->output->writeln('');
    $this->output->writeln("✔️  Project initialized: <info>$projectName</info>\n");

    return Command::SUCCESS;
  }

  /**
   * Installs the project dependencies
   *
   * @return int The command status
   */
  public function install(): int
  {
    $this->output->writeln('');
    $this->output->writeln($this->formatter->formatBlock("Installing project dependencies...", 'question', true));
    $this->output->writeln('');

    printf(
      "%s%s▹▹▹▹▹%s Installation in progress... ☕%s\n\n",
      ColorFX::BLINK->value, Color::FG_LIGHT_BLUE->value, Color::FG_WHITE->value, Color::RESET->value
    );

    $databaseInstaller = new DatabaseInstaller(
      $this->input,
      $this->output,
      $this->formatter,
      $this->questionHelper,
      $this->projectPath ?? ''
    );
    $dependencyInstaller = new ComposerDependencyInstaller(
      $this->input,
      $this->output,
      $this->formatter,
      $this->questionHelper,
      $this->projectPath ?? ''
    );

    // Run the database installer
    if ($status = $databaseInstaller->install()) {
      return $status;
    }

    if ($status = $dependencyInstaller->install() ) {
      return $status;
    }

    return Command::SUCCESS;
  }

  /**
   * Filter the version string
   *
   * @param string $version The version string
   * @return string The filtered version string
   */
  private function filterVersion(string $version): string
  {
    $version = preg_replace('/[^0-9.]/', '', $version);

    return match (true) {
      empty($version),
      ! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) => '0.0.1',
      substr_count($version, '.') < 2 => $version . '.0',
      default => $version
    };
  }

  public function setProjectPath(string $path): void
  {
    $this->projectPath = $path;
  }

  /**
   * Update the namespace in the project files
   *
   * @param string $projectDirectory The project directory
   * @param string $namespace The namespace
   * @return int The command status
   */
  private function updateNamespace(string $projectDirectory, string $namespace): int
  {
    $filePaths = ['bootstrap.php', 'src/AppModule.php', 'src/AppController.php', 'src/AppService.php'];

    if (str_ends_with($namespace, '\\'))
    {
      $namespace = substr($namespace, 0, -1);
    }

    foreach ($filePaths as $path)
    {
      $filePath = Path::join($projectDirectory, $path);

      if (! file_exists($filePath) )
      {
        $this->output->writeln("<error>\n$path file not found</error>");
        return Command::FAILURE;
      }

      $fileContent = file_get_contents($filePath);
      $fileContent = str_replace('Assegai\App', $namespace, $fileContent ?: '');

      if (false === file_put_contents($filePath, $fileContent) )
      {
        $this->output->writeln("<error>\nFailed to update $path file</error>");
        return Command::FAILURE;
      }

      $this->output->writeln("<info>Updated namespace in $path file to $namespace</info>", OutputInterface::VERBOSITY_VERBOSE);
    }

    return Command::SUCCESS;
  }
}