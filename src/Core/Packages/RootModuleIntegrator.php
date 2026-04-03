<?php

namespace Assegai\Console\Core\Packages;

use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class RootModuleIntegrator
{
  public static function resolveRootModuleFilename(string $workspace): ?string
  {
    $bootstrapFile = Path::join($workspace, BOOTSTRAP_FILE);

    if (is_file($bootstrapFile)) {
      $contents = file_get_contents($bootstrapFile);

      if ($contents !== false && preg_match('/AssegaiFactory::(?:create|createFromProject)\(\s*([\\\\A-Za-z0-9_]+)::class/', $contents, $matches)) {
        $candidate = trim($matches[1]);
        $basename = basename(str_replace('\\', '/', $candidate));

        if ($basename !== '') {
          return preg_replace('/\.php$/', '', $basename) ?: null;
        }
      }
    }

    $defaultRootModule = Path::join($workspace, 'src', 'AppModule.php');

    return is_file($defaultRootModule) ? 'AppModule' : null;
  }

  /**
   * @param string[] $useStatements
   * @param string[] $imports
   */
  public static function importModule(string $workspace, array $useStatements, array $imports, OutputInterface $output): int
  {
    $moduleFilename = self::resolveRootModuleFilename($workspace);

    if ($moduleFilename === null) {
      $output->writeln('<comment>Skipped root module update because the root module could not be detected.</comment>');
      return Command::SUCCESS;
    }

    $previousWorkingDirectory = getcwd() ?: $workspace;
    chdir($workspace);

    try {
      return update_module_file([
        'use' => $useStatements,
        'imports' => $imports,
      ], $moduleFilename, $output);
    } finally {
      chdir($previousWorkingDirectory);
    }
  }
}
