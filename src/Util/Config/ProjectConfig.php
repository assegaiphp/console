<?php

namespace Assegai\Console\Util\Config;

use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The project configuration class. This class is used to load and store project configuration values.
 *
 * @package Assegai\Console\Util\Config
 */
class ProjectConfig
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

    $assegaiJsonPath = Path::join($this->workingDirectory, 'assegai.json');

    if (! $assegaiJsonPath )
    {
      $this->output->writeln("<error>Project config not found</error>");

      return Command::FAILURE;
    }

    $configContents = file_get_contents($assegaiJsonPath);

    if ($configContents === false)
    {
      $this->output->writeln("<error>Failed to load project config file contents</error>");
      return Command::FAILURE;
    }

    $configObject = json_decode($configContents);

    foreach ($configObject as $property => $value)
    {
      if (property_exists($this, $property))
      {
        if ($property === 'development')
        {
          $this->development = new DevelopmentConfig();
          $this->development->loadFromObject($value);
        }
        else if ($property === 'scripts')
        {
          $this->$property = json_decode(json_encode($value) ?: '', true);
        }
        else
        {
          $this->$property = $value;
        }
      }
    }

    return Command::SUCCESS;
  }

  /**
   * Returns the value of the property of given name if it exists.
   *
   * @param string $propertyName The property name
   * @return mixed The value of the given property if it exists, otherwise null.
   */
  public function get(string $propertyName): mixed
  {
    if (property_exists($this, $propertyName))
    {
      return $this->$propertyName;
    }

    return null;
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
}