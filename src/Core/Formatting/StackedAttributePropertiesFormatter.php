<?php

namespace Assegai\Console\Core\Formatting;

/**
 * Class StackedAttributePropertiesFormatter. This class formats the attribute properties in a stacked format.
 *
 * @package Assegai\Console\Core\Formatting
 */
class StackedAttributePropertiesFormatter extends AbstractAttributePropertiesFormatter
{

  /**
   * @inheritDoc
   */
  public function getFormatted(string $input): string
  {
    $this->extractValues($input);

    $valueString = "[\n";
    foreach ($this->values as $value)
    {
      $valueString .= str_pad("$value,\n", 4, " ", STR_PAD_LEFT);
    }
    $valueString = rtrim($valueString, ",\n") . "\n";
    $valueString .= "  ]";

    return "$this->propertyName: $valueString";
  }
}