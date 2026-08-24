<?php

namespace Assegai\Console;

use Assegai\Console\Commands\Api\ApiClient;
use Assegai\Console\Commands\Api\ApiExport;
use Assegai\Console\Commands\Add;
use Assegai\Console\Commands\Config;
use Assegai\Console\Commands\DumpAutoload;
use Assegai\Console\Commands\Generate;
use Assegai\Console\Commands\GlobalUpdate;
use Assegai\Console\Commands\Info;
use Assegai\Console\Commands\NewProject;
use Assegai\Console\Commands\Queue\QueueList;
use Assegai\Console\Commands\Queue\QueueWork;
use Assegai\Console\Commands\Schematic\SchematicInit;
use Assegai\Console\Commands\Schematic\SchematicList;
use Assegai\Console\Commands\Serve;
use Assegai\Console\Commands\Test;
use Assegai\Console\Commands\Update;
use Assegai\Console\Commands\Version;
use Assegai\Console\Commands\WebComponents\BuildWebComponents;
use Assegai\Console\Commands\WebComponents\ListWebComponents;
use Assegai\Console\Commands\WebComponents\WatchWebComponents;
use Assegai\Console\Core\Packages\InstalledPackageExtensionLoader;
use Assegai\Console\Util\CliInputNormalizer;
use Assegai\Console\Util\CliVersionChecker;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

class ApplicationFactory
{
  /**
   * @param string[]|null $argv
   */
  public static function create(
    ?string $workspace = null,
    ?array $argv = null,
    ?CliVersionChecker $versionChecker = null,
  ): Application
  {
    $versionChecker ??= CliVersionChecker::forRunningCli();
    $normalizedArgv = CliInputNormalizer::normalize($argv ?? ($_SERVER['argv'] ?? []));
    $application = new AssegaiApplication($versionChecker, $normalizedArgv);
    $application->addCommands(self::builtinCommands($versionChecker));

    $resolvedWorkspace = self::resolveWorkspace($workspace, $normalizedArgv);

    foreach (InstalledPackageExtensionLoader::load($resolvedWorkspace) as $extension) {
      $application->addCommands($extension->instantiateCommands());
    }

    $application->setDefaultCommand('info');

    return $application;
  }

  /**
   * @return Command[]
   */
  private static function builtinCommands(CliVersionChecker $versionChecker): array
  {
    return [
      new ApiClient(),
      new ApiExport(),
      new Add(),
      new Config(),
      new DumpAutoload(),
      new Generate(),
      new GlobalUpdate($versionChecker),
      new Info(),
      new NewProject(),
      new QueueList(),
      new QueueWork(),
      new SchematicInit(),
      new SchematicList(),
      new Serve(),
      new Test(),
      new Update(),
      new Version(),
      new BuildWebComponents(),
      new ListWebComponents(),
      new WatchWebComponents(),
    ];
  }

  /**
   * @param string[] $argv
   */
  private static function resolveWorkspace(?string $workspace, array $argv): string
  {
    if (is_string($workspace) && trim($workspace) !== '') {
      return Path::normalize($workspace);
    }

    $fallback = Path::normalize(getcwd() ?: '.');

    foreach ($argv as $index => $argument) {
      if (!is_string($argument)) {
        continue;
      }

      if (str_starts_with($argument, '--directory=')) {
        return Path::normalize(substr($argument, strlen('--directory=')));
      }

      if (($argument === '--directory' || $argument === '-d') && isset($argv[$index + 1])) {
        return Path::normalize($argv[$index + 1]);
      }
    }

    return $fallback;
  }
}
