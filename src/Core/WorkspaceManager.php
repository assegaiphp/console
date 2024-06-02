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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class WorkspaceManager
{
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected FormatterHelper $formatter,
    protected QuestionHelper $questionHelper
  )
  {
  }

  public function init(?string &$projectName = null, ?string $workingDirectory = null): int
  {
    $workingDirectory = $directory ?? getcwd();
    $templatePath = Path::getTemplatesDirectory();
    $defaultProjectName = DEFAULT_PROJECT_NAME;

    $this->output->writeln($this->formatter->formatBlock("Initializing the project...", 'question', true));
    $this->output->writeln('');

    if (! $projectName )
    {
      $projectNameQuestion = new Question("<info>?</info> Project name: ($defaultProjectName) ", $defaultProjectName);
      $projectName = $this->questionHelper->ask($this->input, $this->output, $projectNameQuestion);
    }
    $projectNameText = new Text($projectName);
    $projectDirectory = Path::join($workingDirectory, $projectNameText->kebabCase());

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
    $defaultVersion = DEFAULT_PROJECT_VERSION ?? '0.0.1';
    $version = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Version: ($defaultVersion) ", $defaultVersion));
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

    if (! file_put_contents($targetAssegaiConfigPath, json_encode($assegaiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) )
    {
      $this->output->writeln("<error>\nFailed to create assegai.json file</error>");
      return Command::FAILURE;
    }

    $projectNameText = new Text($projectName);
    $defaultPackageName = 'assegaiphp/' . $projectNameText->snakeCase();
    $packageName = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Package name: ($defaultPackageName) ", $defaultPackageName));
    [$vendor, $package] = explode('/', $packageName);
    $defaultNamespace = Text::snakeCaseToPascalCase($vendor) . '\\' . Text::snakeCaseToPascalCase($package) . '\\';
    $namespace = $this->questionHelper->ask($this->input, $this->output, new Question("<info>?</info> Namespace: ($defaultNamespace) ", $defaultNamespace));

    $composerConfig = [
      "name" => $packageName,
      "description" => $description ,
      "type" => $type,
      "scripts" => [
        "start" => "php -S localhost:5000 assegai-router.php",
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

    if (! file_put_contents($targetComposerConfigPath, json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) )
    {
      $this->output->writeln("<error>\nFailed to create composer.json file</error>");
      return Command::FAILURE;
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
    printf(
      "%s%s▹▹▹▹▹%s Installation in progress... ☕%s\n",
      ColorFX::BLINK->value, Color::FG_LIGHT_BLUE->value, Color::FG_WHITE->value, Color::RESET->value
    );

    $databaseInstaller = new DatabaseInstaller($this->input, $this->output, $this->formatter, $this->questionHelper);
    $dependencyInstaller = new ComposerDependencyInstaller($this->input, $this->output, $this->formatter, $this->questionHelper);

    // Run the database installer
    if ($status = $databaseInstaller->install())
    {
      return $status;
    }

    if ($status = $dependencyInstaller->install() )
    {
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
}