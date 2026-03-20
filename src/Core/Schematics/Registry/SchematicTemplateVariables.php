<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Assegai\Console\Util\Text;

final class SchematicTemplateVariables
{
  /**
   * @param array<string, mixed> $arguments
   * @param array<string, mixed> $options
   * @return array<string, string>
   */
  public static function build(
    string $requestedName,
    string $baseNamespace = DEFAULT_NAMESPACE,
    string $sourceRoot = 'src',
    array $arguments = [],
    array $options = [],
  ): array {
    $requestedName = trim(str_replace('\\', '/', $requestedName), '/');
    $rawName = $requestedName === '' ? 'GeneratedItem' : basename($requestedName);
    $subdirectory = dirname($requestedName);

    if ($subdirectory === '.' || $subdirectory === DIRECTORY_SEPARATOR) {
      $subdirectory = '';
    }

    $nameText = new Text($rawName);
    $singularName = new Text($nameText->getSingularForm());
    $pluralName = new Text($singularName->getPluralForm());

    $subdirectorySegments = $subdirectory === ''
      ? []
      : array_values(array_filter(explode('/', $subdirectory), static fn(string $segment): bool => $segment !== ''));

    $subdirectoryNamespace = implode('\\', array_map(
      static fn(string $segment): string => (new Text($segment))->pascalCase(),
      $subdirectorySegments
    ));

    $currentNamespace = trim(implode('\\', array_filter([
      trim($baseNamespace, '\\'),
      $subdirectoryNamespace,
      $nameText->pascalCase(),
    ])), '\\');

    $variables = [
      '__NAME__' => $nameText->pascalCase(),
      '__KEBAB__' => $nameText->kebabCase(),
      '__CAMEL__' => $nameText->camelCase(),
      '__SINGULAR_LC__' => strtolower($singularName->pascalCase()),
      '__SINGULAR_CAMEL__' => $singularName->camelCase(),
      '__SINGULAR__' => $singularName->pascalCase(),
      '__PLURAL_KEBAB__' => $pluralName->kebabCase(),
      '__PLURAL_LC__' => strtolower($pluralName->pascalCase()),
      '__PLURAL__' => $pluralName->pascalCase(),
      '__BASE_NAMESPACE__' => trim($baseNamespace, '\\'),
      '__SUBDIRECTORY_NAMESPACE__' => $subdirectoryNamespace,
      '__CURRENT_NAMESPACE__' => $currentNamespace,
      '__SUBDIRECTORY__' => $subdirectory,
      '__SOURCE_ROOT__' => trim($sourceRoot, '/'),
    ];

    foreach ($arguments as $name => $value) {
      $variables['__ARG_' . self::normalizeName($name) . '__'] = self::stringifyValue($value);
    }

    foreach ($options as $name => $value) {
      $variables['__OPTION_' . self::normalizeName($name) . '__'] = self::stringifyValue($value);
    }

    return $variables;
  }

  /**
   * @param array<string, string> $variables
   */
  public static function replace(string $content, array $variables): string
  {
    return str_replace(array_keys($variables), array_values($variables), $content);
  }

  private static function normalizeName(string $name): string
  {
    return strtoupper(str_replace('-', '_', preg_replace('/[^a-zA-Z0-9]+/', '_', $name) ?? $name));
  }

  private static function stringifyValue(mixed $value): string
  {
    if (is_array($value)) {
      return implode(',', array_map(static fn(mixed $item): string => self::stringifyValue($item), $value));
    }

    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if ($value === null) {
      return '';
    }

    return (string) $value;
  }
}
