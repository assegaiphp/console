<?php

namespace Assegai\Console\Util\Config;

use Assegai\Console\Util\Config\Interfaces\ConfigInterface;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DatabaseConfig. This class is a configuration class for the database.
 *
 * @package Assegai\Console\Util\Config
 */
class DBConfig implements ConfigInterface
{
  /**
   * @var array<string, mixed> $config The configuration array.
   */
  protected array $config = [];
  /**
   * @var string $path The path to the configuration file.
   */
  protected string $path = '';
  /**
   * @var Inspector $inspector The inspector.
   */
  protected Inspector $inspector;
  /**
   * @var string[] $possibleConfigFilenames The possible configuration filenames.
   */
  protected array $possibleConfigFilenames = ['local.php', 'dev.php', 'default.php'];

  /**
   * DatabaseConfig constructor.
   *
   * @param string $name The name of the database.
   * @param string $type The type of the database.
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $type,
  )
  {
    $this->inspector = new Inspector($this->input, $this->output);
  }

  /**
   * @inheritDoc
   */
  public function load(): int
  {
    $workingDirectory = getcwd() ?: '';
    if (! $this->inspector->isValidWorkspace($workingDirectory) )
    {
      $this->output->writeln('<error>Invalid workspace</error>');
      return Command::FAILURE;
    }

    foreach ($this->possibleConfigFilenames as $configFilename)
    {
      $filename = Path::join($workingDirectory, 'config', $configFilename);
      if (file_exists($filename))
      {
        $this->path = $filename;
        break;
      }
    }

    if (empty($this->path))
    {
      $this->output->writeln('<error>Configuration file not found</error>');
      return Command::FAILURE;
    }

    # Get contents of the configuration file
    $this->config = require $this->path;

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
      if (! array_key_exists($token, $value))
      {
        return $default;
      }

      $value = $value[$token];
    }

    return $value;
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
    $data = array_to_string($this->config);

    $bytes = file_put_contents($this->path, $data);
    if (false === $bytes )
    {
      $this->output->writeln('<error>Failed to write to configuration file</error>');
      return Command::FAILURE;
    }

    $filename = Path::join('config', basename($this->path));
    $this->output->writeln("<question>UPDATE</question> $filename");
    return Command::SUCCESS;
  }
}