<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Symfony\Component\Console\Input\InputArgument;

class SchematicArgumentDefinition
{
  public function __construct(
    public string $name,
    public string $description = '',
    public bool $required = false,
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
    return new self(
      name: (string) ($data['name'] ?? ''),
      description: (string) ($data['description'] ?? ''),
      required: (bool) ($data['required'] ?? false),
      isArray: (bool) ($data['isArray'] ?? false),
      default: $data['default'] ?? null,
    );
  }

  public function toInputArgument(): InputArgument
  {
    $mode = $this->required ? InputArgument::REQUIRED : InputArgument::OPTIONAL;

    if ($this->isArray) {
      $mode |= InputArgument::IS_ARRAY;
    }

    return new InputArgument(
      $this->name,
      $mode,
      $this->description,
      $this->default,
    );
  }
}
