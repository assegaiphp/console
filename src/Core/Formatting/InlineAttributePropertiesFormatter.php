<?php

namespace Assegai\Console\Core\Formatting;

/**
 * Class InlineAttributePropertiesFormatter. This class formats the attribute properties in an inline format.
 *
 * @package Assegai\Console\Core\Formatting
 */
class InlineAttributePropertiesFormatter extends AbstractAttributePropertiesFormatter
{
  /**
   * @inheritDoc
   */
  public function getFormatted(string $input): string
  {
    $this->extractValues($input);

    $valueString = implode(',', $this->getValues());

    return "$this->propertyName: [$valueString]";
  }
}