<?php

namespace Assegai\Console\Tests\Mocks;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

class MockInput implements InputInterface
{
  public function __construct(
    protected array $arguments = [],
    protected array $options = [],
    protected bool $interactive = false
  )
  {
  }

  /**
   * @inheritDoc
   */
  public function getFirstArgument(): ?string
  {
    return $this->arguments[0] ?? null;
  }

  /**
   * @inheritDoc
   */
  public function hasParameterOption(array|string $values, bool $onlyParams = false): bool
  {
    return in_array($this->getFirstArgument(), $values);
  }

  /**
   * @inheritDoc
   */
  public function getParameterOption(array|string $values, float|array|bool|int|string|null $default = false, bool $onlyParams = false): mixed
  {
    return $this->hasParameterOption($values) ? $this->getFirstArgument() : $default;
  }

  /**
   * @inheritDoc
   */
  public function bind(InputDefinition $definition): void
  {
    // Do nothing
  }

  /**
   * @inheritDoc
   */
  public function validate(): void
  {
    // Do nothing
  }

  /**
   * @inheritDoc
   */
  public function getArguments(): array
  {
    return $this->arguments;
  }

  /**
   * @inheritDoc
   */
  public function getArgument(string $name): mixed
  {
    return $this->arguments[$name] ?? null;
  }

  /**
   * @inheritDoc
   */
  public function setArgument(string $name, mixed $value): void
  {
    $this->arguments[$name] = $value;
  }

  /**
   * @inheritDoc
   */
  public function hasArgument(string $name): bool
  {
    return isset($this->arguments[$name]);
  }

  /**
   * @inheritDoc
   */
  public function getOptions(): array
  {
    return $this->options;
  }

  /**
   * @inheritDoc
   */
  public function getOption(string $name): mixed
  {
    return $this->options[$name] ?? null;
  }

  /**
   * @inheritDoc
   */
  public function setOption(string $name, mixed $value): void
  {
    $this->options[$name] = $value;
  }

  /**
   * @inheritDoc
   */
  public function hasOption(string $name): bool
  {
    return isset($this->options[$name]);
  }

  /**
   * @inheritDoc
   */
  public function isInteractive(): bool
  {
    return $this->interactive;
  }

  /**
   * @inheritDoc
   */
  public function setInteractive(bool $interactive): void
  {
    $this->interactive = $interactive;
  }

  /**
   * @inheritDoc
   */
  public function __toString(): string
  {
    return implode(' ', $this->arguments) . ' ' . implode(' ', array_map(fn($key, $value) => "--$key=$value", array_keys($this->options), $this->options));
  }
}