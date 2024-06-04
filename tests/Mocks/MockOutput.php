<?php

namespace Assegai\Console\Tests\Mocks;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MockOutput implements OutputInterface
{
  protected int $verbosity = self::VERBOSITY_NORMAL;
  protected bool $decorated = false;
  protected array $buffer = [];
  protected OutputFormatterInterface $formatter;

  public function __construct()
  {
    $this->formatter = new OutputFormatter();
  }

  /**
   * @inheritDoc
   */
  public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
  {
    foreach ((array) $messages as $message)
    {
      $this->buffer[] = $message;
    }
  }

  /**
   * @inheritDoc
   */
  public function writeln(iterable|string $messages, int $options = 0): void
  {
    $this->write($messages, true, $options);
  }

  /**
   * @inheritDoc
   */
  public function setVerbosity(int $level): void
  {
    $this->verbosity = $level;
  }

  /**
   * @inheritDoc
   */
  public function getVerbosity(): int
  {
    return $this->verbosity;
  }

  /**
   * @inheritDoc
   */
  public function isQuiet(): bool
  {
    return self::VERBOSITY_QUIET === $this->verbosity;
  }

  /**
   * @inheritDoc
   */
  public function isVerbose(): bool
  {
    return self::VERBOSITY_VERBOSE <= $this->verbosity;
  }

  /**
   * @inheritDoc
   */
  public function isVeryVerbose(): bool
  {
    return self::VERBOSITY_VERY_VERBOSE <= $this->verbosity;
  }

  /**
   * @inheritDoc
   */
  public function isDebug(): bool
  {
    return self::VERBOSITY_DEBUG <= $this->verbosity;
  }

  /**
   * @inheritDoc
   */
  public function setDecorated(bool $decorated): void
  {
    $this->decorated = $decorated;
  }

  /**
   * @inheritDoc
   */
  public function isDecorated(): bool
  {
    return $this->decorated;
  }

  public function setFormatter(OutputFormatterInterface $formatter): void
  {
    $this->formatter = $formatter;
  }

  /**
   * @inheritDoc
   */
  public function getFormatter(): OutputFormatterInterface
  {
    return $this->formatter;
  }
}