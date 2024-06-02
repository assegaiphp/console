<?php

namespace Assegai\Console\Util\Config;

use Assegai\Console\Util\Path;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProjectConfig
{
  protected array $config = [];

  /**
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
    protected string $name = '',
    protected string $description = '',
    protected string $version = DEFAULT_PROJECT_VERSION,
    protected string $projectType = DEFAULT_PROJECT_TYPE,
    protected string $root = '',
    protected string $sourceRoot = '',
    protected array $scripts = [],
    protected DevelopmentConfig|array $development = []
  )
  {
  }

  /**
   * Loads the object with values from the project config file, assegai.json if found.
   *
   * @return $this A reference to the ProjectConfig instance.
   */
  public function load(): self
  {
    $projectConfig = Path::join(Path::getProjectRootPath(), 'assegai.json');

    if (! $projectConfig )
    {
      $this->output->writeln("<error>Project config not found</error>");

      return $this;
    }

    $configContents = file_get_contents($projectConfig);

    if ($configContents === false)
    {
      $this->output->writeln("<error>Failed to load project config file contents</error>");
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
          $this->$property = json_decode(json_encode($value), true);
        }
        else
        {
          $this->$property = $value;
        }
      }
    }

    return $this;
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
}