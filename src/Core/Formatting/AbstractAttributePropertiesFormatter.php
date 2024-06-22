<?php

namespace Assegai\Console\Core\Formatting;

use Assegai\Console\Core\Interfaces\FormatterInterface;

/**
 * Class AbstractAttributeFormatter. This class is a base class for all attribute formatters.
 *
 * @package Assegai\Console\Core\Formatting
 */
abstract class AbstractAttributePropertiesFormatter implements FormatterInterface
{
  /**
   * @var array<scalar> The assigned values
   */
  protected array $values = [];

  /**
   * AbstractAttributeFormatter constructor.
   */
  public function __construct(
    protected string $propertyName,
    protected string $pattern = '/(providers|controllers|imports|exports|config)\:\s*\[([\w\:\,\n\s]*)\]/'
  )
  {
    $this->pattern = str_replace('providers|controllers|imports|exports|config', $this->propertyName, $this->pattern);
  }

  /**
   * Returns the RegEx pattern.
   *
   * @return string The pattern.
   */
  public function getPattern(): string
  {
    return $this->pattern;
  }

  /**
   * Extracts the values from the given string.
   *
   * @param string $from The string to extract the values from.
   * @return array<scalar> The extracted values.
   */
  public function extractValues(string $from): array
  {
    if (preg_match_all("$this->pattern", $from, $matches))
    {
      $propertyNames = $matches[1];
      $matchingIndex = array_search($this->propertyName, $propertyNames);
      $this->values = explode(',', $matches[2][$matchingIndex]);
    }

    return $this->values;
  }

  /**
   * Returns the assigned values.
   *
   * @return array<scalar> The assigned values.
   */
  public function getValues(): array
  {
    return $this->values;
  }

  /**
   * Adds the given values to the assigned values.
   *
   * @param array<scalar> $values The values to add.
   * @return void
   */
  public function addValues(array $values): void
  {
    foreach ($values as $value)
    {
      if (!in_array($value, $this->getValues()))
      {
        $this->values[] = $value;
      }
    }
  }
}