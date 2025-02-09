<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The ConfigSchematic class. Useful for generating various config files such as apache, nginx, etc.
 */
class ConfigSchematic implements SchematicInterface
{
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected string $name,
    protected string $path
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    // TODO: Implement configure() method.
  }

  /**
   * @inheritDoc
   */
  public function prepareBuild(): int
  {
    // TODO: Implement prepareBuild() method.

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function build(): int
  {
    // TODO: Implement build() method.

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function finalizeBuild(): int
  {
    // TODO: Implement finalizeBuild() method.

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function prepareTearDown(): int
  {
    // TODO: Implement prepareTearDown() method.

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function tearDown(): int
  {
    // TODO: Implement tearDown() method.

    return Command::SUCCESS;
  }

  /**
   * @inheritDoc
   */
  public function finalizeTearDown(): int
  {
    // TODO: Implement finalizeTearDown() method.

    return Command::SUCCESS;
  }
}