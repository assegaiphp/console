<?php

namespace Assegai\Console\Util\Config;

use Assegai\Console\Util\Config\Interfaces\ConfigInterface;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The project configuration class. This class is used to load and store project configuration values.
 *
 * @package Assegai\Console\Util\Config
 */
class ProjectConfig implements ConfigInterface
{
  /**
   * @var array<string, mixed> $config The project configuration.
   */
  protected array $config = [];

  /**
   * The ProjectConfig constructor.
   *
   * @param InputInterface $input The input interface.
   * @param OutputInterface $output The output interface.
   * @param string|null $workingDirectory The working directory.
   * @param string $name The project name.
   * @param string $description The project description.
   * @param string $version The project version.
   * @param string $projectType The project type. 'project', 'library'
   * @param string $root The root public directory
   * @param string $sourceRoot The root source file directory
   * @param array<string, string> $scripts The list of executable scripts
   * @param DevelopmentConfig|array{server: array{host: string, port: int, openBrowser: bool}} $development Development configurations
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected ?string $workingDirectory = null,
    protected string $name = '',
    protected string $description = '',
    protected string $version = DEFAULT_PROJECT_VERSION,
    protected string $projectType = DEFAULT_PROJECT_TYPE,
    protected string $root = '',
    protected string $sourceRoot = '',
    protected array $scripts = [],
    protected DevelopmentConfig|array $development = [
      'server' => [
        'host' => 'localhost',
        'port' => 8000,
        'openBrowser' => true
      ]
    ]
  )
  {
  }

  /**
   * Loads the object with values from the project config file, assegai.json if found.
   *
   * @return int The status of the load operation.
   */
  public function load(): int
  {
    if (is_null($this->workingDirectory))
    {
      $this->workingDirectory = getcwd() ?: '';
    }

    $configFilename = 'assegai.json';
    $composerJsonPath = Path::join($this->workingDirectory, $configFilename);

    if (! file_exists($composerJsonPath))
    {
      $this->output->writeln("<error>$configFilename not found</error>");
      return Command::FAILURE;
    }

    $this->config = json_decode(file_get_contents($composerJsonPath) ?: '', true);
    $this->development = $this->get('development') ?? $this->development;

    return Command::SUCCESS;
  }


  /**
   * @inheritDoc
   */
  public function get(string $path, mixed $default = null): mixed
  {
    $tokens = explode('.', $path);

    $value = $this->config;

    foreach ($tokens as $token)
    {
      if (! array_key_exists($token, $value) )
      {
        return $default;
      }

      $value = $value[$token];
    }

    return $value;
  }

  /**
   * Updates the project config file with the new values.
   *
   * @param array<string, mixed> $newDatabaseConfig The new config values.
   * @return false|int The number of bytes written to the file, or false on failure.
   */
  public function updateDatabaseConfig(array $newDatabaseConfig, string $projectPath): false|int
  {
    $configPath = Path::join($projectPath, 'config', 'default.php');
    $oldDatabaseConfig = require($configPath);

    if (! isset($oldDatabaseConfig['databases']) )
    {
      $oldDatabaseConfig['databases'] = [];
    }

    $databaseConfig = $oldDatabaseConfig;
    $databaseConfig['databases'] = array_merge($oldDatabaseConfig['databases'], $newDatabaseConfig['databases']);
    $configContent = "<?php\n\nreturn " . array_to_string($databaseConfig) . ';';

    return file_put_contents($configPath, $configContent);
  }

  /**
   * @inheritDoc
   */
  public function has(string $path): bool
  {
    return null !== $this->get($path);
  }

  /**
   * @inheritDoc
   */
  public function set(string $path, mixed $value): void
  {
    $tokens = explode('.', $path);

    $target = &$this->config;

    foreach ($tokens as $token)
    {
      if (! array_key_exists($token, $target))
      {
        $target[$token] = [];
      }

      $target = &$target[$token];
    }

    $target = $value;
  }

  /**
   * @inheritDoc
   */
  public function commit(): int
  {
    $composerJsonPath = Path::join($this->workingDirectory ?? '', 'assegai.json');
    if (false === file_put_contents($composerJsonPath, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) )
    {
      $this->output->writeln('<error>Failed to write to composer.json</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}