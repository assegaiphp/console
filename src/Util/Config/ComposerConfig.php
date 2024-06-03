<?php

namespace Assegai\Console\Util\Config;

use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ComposerConfig
{
  protected array $composerJson = [];

  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected ?string $workingDirectory = null
  )
  {
  }

  public function load(): int
  {
    if (is_null($this->workingDirectory))
    {
      $this->workingDirectory = getcwd();
    }

    $composerJsonPath = Path::join($this->workingDirectory, 'composer.json');

    if (! file_exists($composerJsonPath))
    {
      $this->output->writeln('<error>composer.json not found</error>');
      return Command::FAILURE;
    }

    $this->composerJson = json_decode(file_get_contents($composerJsonPath), true);

    return Command::SUCCESS;
  }

  /**
   * Get a value from the composer.json file
   *
   * @param string $path
   * @return mixed
   */
  public function get(string $path): mixed
  {
    $tokens = explode('.', $path);

    $value = $this->composerJson;

    foreach ($tokens as $token)
    {
      if (! array_key_exists($token, $value))
      {
        return null;
      }

      $value = $value[$token];
    }

    return $value;
  }
}