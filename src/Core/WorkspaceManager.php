<?php

namespace Assegai\Console\Core;

use Assegai\Console\Installers\ComposerDependencyInstaller;
use Assegai\Console\Installers\DatabaseInstaller;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class WorkspaceManager. Manages the workspace.
 *
 * @package Assegai\Console\Core
 */
class WorkspaceManager
{
  protected ?string $projectPath = null;
  protected CliPrompt $prompts;

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
    $this->prompts = new CliPrompt($this->input, $this->output);
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

    $this->renderProjectIntro($workingDirectory ?: '');

    if (! $projectName ) {
      $projectName = $this->prompts->text(
        'Project name',
        $defaultProjectName,
        'Used for the folder name. Kebab-case works well.',
        'blog-api',
        true
      );
    }

    $projectName = trim($projectName);

    $projectNameText = new Text($projectName);
    $projectDirectory = Path::join($workingDirectory ?: '', $projectNameText->kebabCase());
    $this->projectPath = $projectDirectory;

    if ( file_exists($projectDirectory) ) {
      $this->output->writeln("<error>Project directory already exists: $projectDirectory</error>");
      return Command::FAILURE;
    }

    if (! mkdir($projectDirectory, 0777, true) ) {
      $this->output->writeln("<error>\nFailed to create project directory: $projectDirectory</error>");
      return Command::FAILURE;
    }

    if (! copy_directory($templatePath, $projectDirectory) ) {
      $this->output->writeln("<error>\nFailed to copy project template</error>");
      return Command::FAILURE;
    }

    $description = $this->prompts->text(
      'Description',
      '',
      'Optional. This is written to composer.json and assegai.json.',
      'A modular PHP API built with AssegaiPHP'
    );
    $defaultVersion = DEFAULT_PROJECT_VERSION;
    $version = $this->prompts->text(
      'Version',
      $defaultVersion,
      'Use semantic versioning.',
      '0.0.1',
      true,
      fn(string $value): ?string => $this->validateVersion($value)
    );
    $type = 'project';

    $assegaiConfig = ProjectTemplateDefaults::hydrateAssegaiConfig([
      'name' => $projectName,
      'description' => $description,
      'version' => $version,
      'projectType' => $type,
    ]);
    $targetAssegaiConfigPath = Path::join($projectDirectory, 'assegai.json');

    if (! file_put_contents($targetAssegaiConfigPath, json_encode($assegaiConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) ) {
      $this->output->writeln("<error>\nFailed to create assegai.json file</error>");
      return Command::FAILURE;
    }

    $projectNameText = new Text($projectName);
    $defaultPackageName = $this->buildDefaultPackageName($projectNameText);
    $packageName = strtolower(trim($this->prompts->text(
      'Package name',
      $defaultPackageName,
      'Use Composer format: vendor/package-name',
      'acme/blog-api',
      true,
      fn(string $value): ?string => $this->validatePackageName($value)
    )));
    $defaultNamespace = $this->buildDefaultNamespace($packageName);
    $namespace = trim($this->prompts->text(
      'Namespace',
      $defaultNamespace,
      'Use a PSR-4 namespace, for example Acme\\BlogApi\\',
      'Acme\\BlogApi\\',
      true,
      fn(string $value): ?string => $this->validateNamespace($value)
    ));

    if (! str_ends_with($namespace, '\\') ) {
      $namespace .= '\\';
    }

    $this->renderProjectSummary($projectName, $projectDirectory, $packageName, $namespace);

    $composerConfig = ProjectTemplateDefaults::hydrateComposerConfig([
      'name' => $packageName,
      'description' => $description,
      'type' => $type,
      'autoload' => [
        'psr-4' => [
          $namespace => 'src/'
        ]
      ]
    ]);
    $targetComposerConfigPath = Path::join($projectDirectory, 'composer.json');

    if (! file_put_contents($targetComposerConfigPath, json_encode($composerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) ) {
      $this->output->writeln("<error>\nFailed to create composer.json file</error>");
      return Command::FAILURE;
    }

    # Update namespace in project files
    if (($statusCode = $this->updateNamespace($projectDirectory, $namespace)) > 0) {
      $this->output->writeln("<error>\nFailed to update namespace in project files</error>");
      return $statusCode;
    }

    # Copy .env.example to .env
    $envExamplePath = Path::join($projectDirectory, '.env.example');
    $envPath = Path::join($projectDirectory, '.env');
    if ( file_exists($envExamplePath) ) {
      if (! copy($envExamplePath, $envPath) ) {
        $this->output->writeln("<error>\nFailed to create .env file</error>");
        return Command::FAILURE;
      }
    }

    if (Command::SUCCESS !== $this->generateApplicationSecretKey($envPath)) {
      $this->output->writeln([
        "<error>\nFailed to generate application secret key</error>",
        "<comment>Please generate one manually and set it in the .env file</comment>"
      ]);
    }

    # Initialize the git repository
    if (
      ! boolval($this->input->getOption('skip-git')) &&
      is_installed('git') &&
      $this->prompts->confirm('Initialize git repository?', false, hint: 'A git repository helps you start tracking changes immediately.')
    ) {
      $this->output->writeln('');
      $this->output->writeln(
        $this->formatter->formatBlock('Initializing git repository...', 'question', true),
        OutputInterface::VERBOSITY_VERBOSE
      );
      $gitInitCommand = "cd " . escapeshellarg($projectDirectory) . " && git init";
      $gitInit = shell_exec($gitInitCommand);

      if (! str_contains($gitInit, 'Initialized empty Git repository') ) {
        $this->output->writeln("<error>\nFailed to initialize git repository</error>");
        return Command::FAILURE;
      }
    }

    $this->output->writeln(["", "✔️  Project initialized: <info>$projectName</info>", ""]);

    return Command::SUCCESS;
  }

  /**
   * Installs the project dependencies
   *
   * @return int The command status
   */
  public function install(): int
  {
    $this->output->writeln([
        '',
        '<fg=gray>We will configure optional services first, then install the project dependencies.</>'
    ]);

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

  protected function buildDefaultPackageName(Text $projectName): string
  {
    return 'assegaiphp/' . $projectName->kebabCase();
  }

  protected function buildDefaultNamespace(string $packageName): string
  {
    [$vendor, $package] = explode('/', $packageName, 2);

    return (new Text($vendor))->pascalCase() . '\\' . (new Text($package))->pascalCase() . '\\';
  }

  protected function validateVersion(string $version): ?string
  {
    $version = trim($version);

    if ($version === '') {
      return 'Version is required.';
    }

    if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
      return 'Use semantic versioning, for example 0.1.0.';
    }

    return null;
  }

  protected function validatePackageName(string $packageName): ?string
  {
    $packageName = strtolower(trim($packageName));

    if ($packageName === '') {
      return 'Package name is required.';
    }

    if (! preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $packageName)) {
      return 'Use vendor/package-name format in lowercase.';
    }

    return null;
  }

  protected function validateNamespace(string $namespace): ?string
  {
    $namespace = trim($namespace);

    if ($namespace === '') {
      return 'Namespace is required.';
    }

    $namespace = rtrim($namespace, '\\');

    if (! preg_match('/^(?:[A-Z][A-Za-z0-9_]*\\\\)*[A-Z][A-Za-z0-9_]*$/', $namespace)) {
      return 'Use a PSR-4 namespace such as Acme\\BlogApi\\.';
    }

    return null;
  }

  protected function renderProjectIntro(string $workingDirectory): void
  {
    $this->output->writeln($this->formatter->formatBlock('Create a New AssegaiPHP Project', 'question', true));
    $this->output->writeln([
        '',
        '<fg=gray>We will scaffold the workspace, wire autoloading, and optionally configure a database.</>'
    ]);

    if ($workingDirectory !== '') {
      $this->output->writeln(["", "<fg=gray>Target directory: $workingDirectory</>"]);
    }

    $this->output->writeln(['<fg=gray>Press enter to accept any visible default.</>', '']);
  }

  protected function renderProjectSummary(
    string $projectName,
    string $projectDirectory,
    string $packageName,
    string $namespace,
  ): void
  {
    $this->output->writeln('');
    $this->output->writeln($this->formatter->formatBlock('Scaffolding Project', 'question', true));
    $this->output->writeln([
        "",
        "<fg=gray>Project</>   <info>$projectName</info>",
        "<fg=gray>Path</>      <comment>$projectDirectory</comment>",
        "<fg=gray>Package</>   <comment>$packageName</comment>",
        "<fg=gray>Namespace</> <comment>{$namespace}</comment>",
        ""
    ]);
  }

  public function setProjectPath(string $path): void
  {
    $this->projectPath = $path;
  }

  public function getProjectPath(): ?string
  {
    return $this->projectPath;
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

  /**
   * Generate the application secret key and update the .env file
   *
   * @param string $envPath The path to the .env file
   * @return int The command status
   */
  private function generateApplicationSecretKey(string $envPath): int
  {
    try {
      $keyLength = 32;
      $binaryKey = random_bytes($keyLength);
      $secretKey = bin2hex($binaryKey);

      # Update .env file with the secret key
      if ( file_exists($envPath) ) {
        $envContent = file_get_contents($envPath);

        # Check if APP_SECRET_KEY already exists
        if (str_contains($envContent ?: '', 'APP_SECRET_KEY') === false) {
          $envContent .= "\nAPP_SECRET_KEY=[YOUR_SECRET_KEY]\n";
        }

        $envContent = preg_replace('/APP_SECRET_KEY=.*/', "APP_SECRET_KEY=$secretKey", $envContent ?: '');

        if (false === file_put_contents($envPath, $envContent) ) {
          $this->output->writeln("<error>\nFailed to update .env file with secret key</error>");
          return Command::FAILURE;
        }
      }

      return Command::SUCCESS;
    } catch (Throwable $exception) {
      $this->output->writeln("<error>$exception</error>", OutputInterface::VERBOSITY_VERBOSE);
      return Command::FAILURE;
    }
  }
}
