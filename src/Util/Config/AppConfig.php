<?php

namespace Assegai\Console\Util\Config;

use Assegai\Console\Util\Config\Interfaces\ConfigInterface;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The AppConfig class. This class is responsible for managing the application configuration.
 *
 * @package Assegai\Console\Util\Config
 */
class AppConfig implements ConfigInterface
{
  /**
   * @var string[] $possibleConfigFilenames The configuration filenames.
   */
  protected array $possibleConfigFilenames = ['default.php', 'dev.php', 'local.php', 'secure.php'];

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
   * AppConfig constructor.
   *
   * @param InputInterface $input The input interface.
   * @param OutputInterface $output The output interface.
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output
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
    if (! $this->inspector->isValidWorkspace($workingDirectory) ) {
      $this->output->writeln('<error>Invalid workspace</error>');
      return Command::FAILURE;
    }

    foreach ($this->possibleConfigFilenames as $configFilename) {
      $filename = Path::join($workingDirectory, 'config', $configFilename);
      if (file_exists($filename)) {
        $this->path = $filename;
        break;
      }
    }

    if (empty($this->path)) {
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

    $value = $this->config ?? [];

    foreach ($tokens as $token) {
      if (! array_key_exists($token, $value)) {
        return $default;
      }

      $value = $value[$token];
    }

    return $value;
  }

  /**
   * @inheritDoc
   */
  public function has(string $path): bool
  {
    return $this->get($path) !== null;
  }

  /**
   * @inheritDoc
   */
  public function set(string $path, mixed $value): void
  {
    $tokens = explode('.', $path);

    $target = &$this->config;

    foreach ($tokens as $token) {
      if (! array_key_exists($token, $target)) {
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

    $bytes = file_put_contents($this->path, <<<PHP
<?php

return $data;
PHP
    );
    if (false === $bytes ) {
      $this->output->writeln('<error>Failed to write to configuration file</error>');
      return Command::FAILURE;
    }

    $filename = Path::join('config', basename($this->path));
    $this->output->writeln("<question>UPDATE</question> $filename");
    return Command::SUCCESS;
  }
}