<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Symfony\Component\Console\Input\InputOption;

class SchematicOptionDefinition
{
  public function __construct(
    public string $name,
    public ?string $shortcut = null,
    public string $description = '',
    public bool $acceptValue = false,
    public bool $valueRequired = false,
    public bool $isArray = false,
    public mixed $default = null,
  )
  {
  }

  /**
   * @param array<string, mixed> $data
   */
  public static function fromArray(array $data): self
  {
    $acceptValue = (bool) ($data['acceptValue'] ?? false);
    $valueRequired = $acceptValue ? (bool) ($data['valueRequired'] ?? false) : false;

    return new self(
      name: (string) ($data['name'] ?? ''),
      shortcut: isset($data['shortcut']) ? (string) $data['shortcut'] : null,
      description: (string) ($data['description'] ?? ''),
      acceptValue: $acceptValue,
      valueRequired: $valueRequired,
      isArray: $acceptValue ? (bool) ($data['isArray'] ?? false) : false,
      default: $data['default'] ?? null,
    );
  }

  public function toInputOption(): InputOption
  {
    $mode = InputOption::VALUE_NONE;

    if ($this->acceptValue) {
      $mode = $this->valueRequired ? InputOption::VALUE_REQUIRED : InputOption::VALUE_OPTIONAL;

      if ($this->isArray) {
        $mode |= InputOption::VALUE_IS_ARRAY;
      }
    }

    return new InputOption(
      $this->name,
      $this->shortcut,
      $mode,
      $this->description,
      $this->default,
    );
  }
}
