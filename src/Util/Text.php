<?php

namespace Assegai\Console\Util;

use plejus\PhpPluralize\Inflector;
use Stringable;

/**
 * Represents a text.
 *
 * @package Assegai\Console\Util
 */
class Text implements Stringable
{
  /**
   * Text constructor.
   *
   * @param string $value The text value.
   */
  public function __construct(protected string $value)
  {
  }

  /**
   * Returns the length of the text.
   *
   * @return int The length of the text.
   */
  public function length(): int
  {
    return strlen($this->value);
  }

  /**
   * Returns the character at the specified index.
   *
   * @param int $index The index of the character.
   * @return string|null The character at the specified index.
   */
  public function charAt(int $index): ?string
  {
    return $this->value[$index] ?? null;
  }

  /**
   * Returns the substring of the text.
   *
   * @param Text|string $text The text to get the substring from.
   * @param int $offset The offset. Default is 0.
   * @param int|null $length The length. Default is null.
   * @return string
   */
  public function substring(Text|string $text, int $offset = 0, ?int $length = null): string
  {
    return substr($this->value, $offset, $length);
  }

  /**
   * Compares this text to another text.
   *
   * @param Text $text The text to compare to.
   * @param bool $ignoreCase Whether to ignore the case.
   * @return int
   */
  public function compareTo(Text $text, bool $ignoreCase = false): int
  {
    if ($ignoreCase)
    {
      return strtolower($this->value) <=> strtolower(strval($text));
    }

    return $this->value <=> strval($text);
  }

  /**
   * Checks if the text is equal to another text.
   *
   * @param Text|string $text The text to compare to.
   * @return bool True if the text is equal to the other text, false otherwise.
   */
  public function equals(Text|string $text): bool
  {
    return $this->compareTo($text) === 0;
  }

  /**
   * Concatenates the text with another text.
   *
   * @param Text|string $text The text to concatenate.
   * @return Text The concatenated text.
   */
  public function concat(Text|string $text): Text
  {
    $this->value .= $text;

    return $this;
  }

  /**
   * Checks if the text is empty.
   *
   * @param Text|string $text The text to check.
   * @return bool True if the text is empty, false otherwise.
   */
  public function contains(Text|string $text): bool
  {
    return str_contains($this->value, $text);
  }

  /**
   * Finds the index of the given text.
   *
   * @param Text|string $text The text to find.
   * @param int $offset The offset. Default is 0.
   * @return false|int The index of the text, or false if not found.
   */
  public function indexOf(Text|string $text, int $offset = 0): false|int
  {
    return strpos($this->value, $text, $offset);
  }

  /**
   * Finds the last index of the given text.
   *
   * @param Text|string $text The text to find.
   * @param int $offset The offset. Default is 0.
   * @return int The last index of the text.
   */
  public function lastIndexOf(Text|string $text, int $offset = 0): int
  {
    return strripos($this->value, $text, $offset);
  }

  /**
   * Determines whether the text is empty.
   *
   * @return bool True if the text is empty, false otherwise.
   */
  public function isEmpty(): bool
  {
    return empty($this->value);
  }

  /**
   * Determines whether the text is blank. A text is considered blank if it is empty or contains only
   * whitespace characters.
   *
   * @return bool True if the text is blank, false otherwise.
   */
  public function isBlank(): bool
  {
    return ($this->isEmpty() || preg_match('/^\s+$/', $this->value) > 0);
  }

  /**
   * Converts a camel case string to snake case.
   *
   * @param string $input The camel case string.
   * @return string The snake case string.
   */
  public static function camelCaseToSnakeCase(string $input): string
  {
    $length = strlen($input);
    $word = '';
    $tokens = [];

    for ($x = 0; $x < $length; $x++)
    {
      $ch = substr($input, $x, 1);

      if (ctype_upper($ch))
      {
        $tokens[] = $word;
        $word = '';
      }

      $word .= $ch;
    }

    $tokens[] = $word;
    $output = implode('_', $tokens);

    return strtolower($output);
  }

  /**
   * Converts a snake case string to camel case.
   *
   * @param string $input The snake case string.
   * @return string The camel case string.
   */
  public static function snakeCaseToCamelCase(string $input): string
  {
    $replacement = str_replace('_', ' ', $input);
    $buffer = ucwords($replacement);
    $output = str_replace(' ', '', $buffer);

    return lcfirst($output);
  }

  /**
   * Converts a camel case string to snake case.
   *
   * @param string $input The camel case string.
   * @return string The snake case string.
   */
  public static function pascalCaseToSnakeCase(string $input): string
  {
    $output = self::pascalCaseToCamelCase(input: $input);

    return self::camelCaseToSnakeCase(input: $output);
  }

  /**
   * Converts a snake case string to pascal case.
   *
   * @param string $input The snake case string.
   * @return string The pascal case string.
   */
  public static function snakeCaseToPascalCase(string $input): string
  {
    $tokens = explode('_', $input);

    $output = array_map(function ($token) {
      return strtoupper(substr($token, 0, 1)) . strtolower(substr($token, 1));
    }, $tokens);

    return implode($output);
  }

  /**
   * Converts a camel case string to pascal case.
   *
   * @param string $input The camel case string.
   * @return string The pascal case string.
   */
  public static function pascalCaseToCamelCase(string $input): string
  {
    return lcfirst($input);
  }

  /**
   * Converts a pascal case string to snake case.
   *
   * @param string $input The pascal case string.
   * @return string The snake case string.
   */
  public static function camelCaseToPascalCase(string $input): string
  {
    return ucfirst($input);
  }

  /**
   * Converts a camel case string to kebab case.
   *
   * @param string $input The camel case string.
   * @return string The kebab case string.
   */
  public static function kebabToSnakeCase(string $input): string
  {
    return str_replace('-', '_', $input);
  }

  /**
   * Converts a kebab case string to camel case.
   *
   * @param string $input The kebab case string.
   * @return string The camel case string.
   */
  public static function kebabToCamelCase(string $input): string
  {
    $tokens = explode('-', $input);
    $output = '';

    foreach ($tokens as $index => $token)
    {
      $word = strtolower($token);
      if ($index !== 0)
      {
        $word = ucfirst($word);
      }

      $output .= $word;
    }

    return $output;
  }

  /**
   * Converts a kebab case string to pascal case.
   *
   * @param string $input The kebab case string.
   * @return string The pascal case string.
   */
  public static function kebabToPascal(string $input): string
  {
    return ucfirst(self::kebabToCamelCase($input));
  }

  /**
   * Returns the kebab case of this text.
   *
   * @return string The kebab case of the text.
   */
  public function kebabCase(): string
  {
    $tokens = preg_split('/[\W_]/', $this->value);
    $output = [];

    foreach ($tokens as $token)
    {
      $output[] = strtolower($token);
    }

    return implode('-', $output);
  }

  /**
   * Returns the snake case of the word.
   *
   * @return string The snake case of the word.
   */
  public function snakeCase(): string
  {
    $tokens = preg_split('/\W/', $this->value);
    $output = [];

    foreach ($tokens as $token)
    {
      $output[] = strtolower($token);
    }

    return implode('_', $output);
  }

  /**
   * Returns the pascal case of the word.
   *
   * @return string The pascal case of the word.
   */
  public function pascalCase(): string
  {
    $tokens = preg_split('/[\W_]/', $this->value);
    $output = [];
    foreach ($tokens as $token)
    {
      $output[] = ucfirst($token);
    }

    return implode('', $output);
  }

  /**
   * Returns the title case of the word.
   *
   * @return string
   */
  public function titleCase(): string
  {
    $tokens = preg_split('/[\W_]/', $this->value);
    $output = [];
    foreach ($tokens as $token)
    {
      $output[] = ucfirst($token);
    }

    return implode(' ', $output);
  }

  /**
   * Returns the sentence case of the word.
   *
   * @return string The sentence case of the word.
   */
  public function sentenceCase(): string
  {
    $tokens = preg_split('/[!.?]/', $this->value);
    $output = [];
    foreach ($tokens as $token)
    {
      $output[] = strtolower($token);
    }

    $output = implode(' ', $output);
    return ucfirst($output);
  }

  /**
   * Returns the plural form of the word.
   *
   * @return string The plural form of the word.
   */
  public function getPluralForm(): string
  {
    $inflector = new Inflector();
    /* Suppress warning about alphanumeric delimiter in preg_match */
    return @$inflector->plural($this->value);
  }

  /**
   * Returns the singular form of the word.
   *
   * @return string The singular form of the word.
   */
  public function getSingularForm(): string
  {
    $inflector = new Inflector();
    return @$inflector->singular($this->value);
  }

  /**
   * Determines whether a string of text ends with
   *
   * @return bool True if the text ends with punctuation, false otherwise.
   */
  public function endsWithPunctuation(): bool
  {
    return (bool)preg_match('/[!?.]$/', $this->value);
  }

  /**
   * Adds terminal punctuation to given text.
   *
   * @param string $terminator The terminating punctuation. Default is '.'.
   * @return string The text with terminal punctuation.
   */
  public function terminate(string $terminator = '.'): string
  {
    if (is_null($this->value))
    {
      return '';
    }

    if (!in_array($terminator, ['.', '!', '?']))
    {
      $terminator = '.';
    }

    return self::endsWithPunctuation($this->value) ? $this->value : "$this->value$terminator";
  }

  /**
   * @inheritDoc
   */
  public function __toString(): string
  {
    return $this->value;
  }
}