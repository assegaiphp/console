<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\ApplicationSchematic;
use Assegai\Console\Core\Schematics\ClassSchematic;
use Assegai\Console\Core\Schematics\ComponentSchematic;
use Assegai\Console\Core\Schematics\ControllerSchematic;
use Assegai\Console\Core\Schematics\EnumSchematic;
use Assegai\Console\Core\Schematics\GuardSchematic;
use Assegai\Console\Core\Schematics\InterceptorSchematic;
use Assegai\Console\Core\Schematics\InterfaceSchematic;
use Assegai\Console\Core\Schematics\ModuleSchematic;
use Assegai\Console\Core\Schematics\PageSchematic;
use Assegai\Console\Core\Schematics\PipeSchematic;
use Assegai\Console\Core\Schematics\QueueProcessorSchematic;
use Assegai\Console\Core\Schematics\ResourceSchematic;
use Assegai\Console\Core\Schematics\ServiceSchematic;
use Assegai\Console\Core\Schematics\WebComponentSchematic;

class BuiltinSchematicLoader
{
  /**
   * @return SchematicDefinition[]
   */
  public static function load(): array
  {
    $nameArgument = new SchematicArgumentDefinition(
      name: 'name',
      description: 'The name of the schematic to generate.',
      required: true,
    );

    $wcOption = new SchematicOptionDefinition(
      name: 'wc',
      description: 'Generate or pair a Web Component runtime file where supported.',
      acceptValue: false,
    );

    return [
      self::buildDefinition(
        name: 'application',
        aliases: ['application'],
        description: 'Generate a new application workspace',
        requiresWorkspace: false,
        className: ApplicationSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'component',
        aliases: ['cm'],
        description: 'Generate a component declaration',
        className: ComponentSchematic::class,
        arguments: [$nameArgument],
        options: [$wcOption],
      ),
      self::buildDefinition(
        name: 'controller',
        aliases: ['c'],
        description: 'Generate a controller declaration',
        className: ControllerSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'class',
        aliases: ['cl'],
        description: 'Generate a new class',
        className: ClassSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'enum',
        aliases: [],
        description: 'Generate an enum declaration',
        className: EnumSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'guard',
        aliases: ['g'],
        description: 'Generate a guard declaration',
        className: GuardSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'interceptor',
        aliases: ['ic'],
        description: 'Generate an interceptor',
        className: InterceptorSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'interface',
        aliases: ['i'],
        description: 'Generate an interface',
        className: InterfaceSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'module',
        aliases: ['m'],
        description: 'Generate a module declaration',
        className: ModuleSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'page',
        aliases: ['pg'],
        description: 'Generate a page declaration',
        className: PageSchematic::class,
        arguments: [$nameArgument],
        options: [$wcOption],
      ),
      self::buildDefinition(
        name: 'pipe',
        aliases: ['p'],
        description: 'Generate a pipe declaration',
        className: PipeSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'queue-processor',
        aliases: ['qp'],
        description: 'Generate a queue processor provider',
        className: QueueProcessorSchematic::class,
        arguments: [$nameArgument],
        options: [
          new SchematicOptionDefinition(
            name: 'queue',
            description: 'The queue connection path used by generated queue processors.',
            acceptValue: true,
            valueRequired: true,
            default: 'driver.connection',
          ),
          new SchematicOptionDefinition(
            name: 'job',
            description: 'The job class used to type generated queue processor methods.',
            acceptValue: true,
            valueRequired: true,
            default: null,
          ),
        ],
      ),
      self::buildDefinition(
        name: 'resource',
        aliases: ['r'],
        description: 'Generate a new CRUD resource',
        className: ResourceSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'service',
        aliases: ['s'],
        description: 'Generate a service declaration',
        className: ServiceSchematic::class,
        arguments: [$nameArgument],
      ),
      self::buildDefinition(
        name: 'web-component',
        aliases: ['wc'],
        description: 'Generate a standalone Web Component',
        className: WebComponentSchematic::class,
        arguments: [$nameArgument],
      ),
    ];
  }

  /**
   * @param string[] $aliases
   * @param SchematicArgumentDefinition[] $arguments
   * @param SchematicOptionDefinition[] $options
   */
  private static function buildDefinition(
    string $name,
    array $aliases,
    string $description,
    string $className,
    array $arguments,
    array $options = [],
    bool $requiresWorkspace = true,
  ): SchematicDefinition {
    return new SchematicDefinition(
      name: $name,
      aliases: $aliases,
      description: $description,
      requiresWorkspace: $requiresWorkspace,
      sourceType: 'builtin',
      source: 'builtin:' . $name,
      kind: 'builtin',
      arguments: $arguments,
      options: $options,
      metadata: [],
      factory: static function (SchematicContext $context) use ($className): SchematicInterface {
        $schematic = new $className(
          $context->input,
          $context->output,
          $context->getBaseName(),
          $context->getWorkspace(),
          $context->getSubdirectory(),
        );

        if (!$schematic instanceof SchematicInterface) {
          throw new \RuntimeException(sprintf(
            'Built-in schematic class [%s] must implement %s.',
            $className,
            SchematicInterface::class,
          ));
        }

        return $schematic;
      }
    );
  }
}
