<?php

namespace Assegai\Console\Core\Schematics\Registry;

use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SchematicRegistryFactory
{
  public static function build(
    InputInterface $input,
    OutputInterface $output,
    string $directory,
  ): SchematicRegistry {
    $registry = new SchematicRegistry();
    $registry->registerAll(BuiltinSchematicLoader::load());

    $inspector = new Inspector($input, $output);

    if (! $inspector->isValidWorkspace($directory)) {
      return $registry;
    }

    $workspaceAutoload = Path::join($directory, 'vendor', 'autoload.php');

    if (is_file($workspaceAutoload)) {
      require_once $workspaceAutoload;
    }

    $registry->registerAll(LocalSchematicLoader::load($directory));
    $registry->registerAll(PackageSchematicLoader::load($directory));

    return $registry;
  }
}
