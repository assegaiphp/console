<?php

namespace Assegai\Console\Core\Schematics\Registry;

use RuntimeException;
use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Custom\DeclarativeSchematic;

class SchematicManifestLoader
{
  /**
   * @param array<string, mixed> $manifest
   */
  public static function definitionFromManifest(
    array $manifest,
    string $manifestPath,
    string $sourceType,
    string $source,
  ): SchematicDefinition {
    $name = trim((string) ($manifest['name'] ?? ''));

    if ($name === '') {
      throw new RuntimeException(sprintf('Schematic manifest is missing a name: %s', $manifestPath));
    }

    $kind = (string) ($manifest['kind'] ?? 'declarative');

    if (! in_array($kind, ['declarative', 'class'], true)) {
      throw new RuntimeException(sprintf(
        'Schematic "%s" declares an unsupported kind "%s" in %s.',
        $name,
        $kind,
        $manifestPath,
      ));
    }

    $arguments = [];
    foreach (($manifest['arguments'] ?? []) as $argument) {
      if (! is_array($argument)) {
        throw new RuntimeException(sprintf('Schematic "%s" has an invalid argument definition in %s.', $name, $manifestPath));
      }

      $arguments[] = SchematicArgumentDefinition::fromArray($argument);
    }

    $options = [];
    foreach (($manifest['options'] ?? []) as $option) {
      if (! is_array($option)) {
        throw new RuntimeException(sprintf('Schematic "%s" has an invalid option definition in %s.', $name, $manifestPath));
      }

      $options[] = SchematicOptionDefinition::fromArray($option);
    }

    $basePath = dirname($manifestPath);
    $metadata = [
      'manifestPath' => $manifestPath,
      'basePath' => $basePath,
    ];

    if ($kind === 'declarative') {
      $templates = $manifest['templates'] ?? null;

      if (! is_array($templates) || $templates === []) {
        throw new RuntimeException(sprintf(
          'Declarative schematic "%s" must declare at least one template in %s.',
          $name,
          $manifestPath,
        ));
      }

      $metadata['templates'] = $templates;
    } else {
      $handler = $manifest['handler'] ?? null;

      if (! is_array($handler) || ! isset($handler['class']) || ! is_string($handler['class']) || trim($handler['class']) === '') {
        throw new RuntimeException(sprintf(
          'Class-backed schematic "%s" must declare handler.class in %s.',
          $name,
          $manifestPath,
        ));
      }

      $metadata['handler'] = [
        'class' => trim($handler['class']),
        'file' => isset($handler['file']) && is_string($handler['file']) && trim($handler['file']) !== ''
          ? trim($handler['file'])
          : null,
      ];
    }

    return new SchematicDefinition(
      name: $name,
      aliases: array_values(array_filter(
        array_map(static fn(mixed $alias): string => trim((string) $alias), (array) ($manifest['aliases'] ?? [])),
        static fn(string $alias): bool => $alias !== ''
      )),
      description: (string) ($manifest['description'] ?? ''),
      requiresWorkspace: (bool) ($manifest['requiresWorkspace'] ?? true),
      sourceType: $sourceType,
      source: $source,
      kind: $kind,
      arguments: $arguments,
      options: $options,
      metadata: $metadata,
      factory: static function (SchematicContext $context) use ($kind, $metadata): SchematicInterface {
        if ($kind === 'declarative') {
          return new DeclarativeSchematic($context);
        }

        $handler = $metadata['handler'];
        $handlerFile = $handler['file'] ?? null;

        if (is_string($handlerFile) && $handlerFile !== '') {
          require_once SchematicTemplateVariables::replace(
            \Assegai\Console\Util\Path::join($metadata['basePath'], $handlerFile),
            $context->getTemplateVariables(),
          );
        }

        $className = (string) $handler['class'];

        return new $className($context);
      },
    );
  }

  public static function loadFromFile(string $manifestPath, string $sourceType, string $source): SchematicDefinition
  {
    if (! is_file($manifestPath)) {
      throw new RuntimeException(sprintf('Schematic manifest not found: %s', $manifestPath));
    }

    $decoded = json_decode(file_get_contents($manifestPath) ?: '', true);

    if (! is_array($decoded)) {
      throw new RuntimeException(sprintf('Failed to decode schematic manifest: %s', $manifestPath));
    }

    return self::definitionFromManifest($decoded, $manifestPath, $sourceType, $source);
  }
}
