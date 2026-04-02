<?php

namespace Assegai\Console\Commands\WebComponents;

use Assegai\Console\Util\Inspector;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Assegai\Console\WebComponents\HotReload\WebComponentHotReloadState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'wc:watch',
  description: 'Build the Web Components bundle in watch mode.'
)]
class WatchWebComponents extends Command
{
  private const array EXPECTED_SHUTDOWN_EXIT_CODES = [Command::SUCCESS, 130, 143];

  public function configure(): void
  {
    $this
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd())
      ->addOption('no-hot-reload', null, InputOption::VALUE_NONE, 'Disable browser hot reloading while watching.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = $input->getOption('directory') ?: (getcwd() ?: '');
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>Invalid workspace.</error>');
      return Command::FAILURE;
    }

    $builder = $this->createBuilder();
    $components = $builder->discover($workspace);

    if (empty($components)) {
      $output->writeln('<comment>No Web Components found.</comment>');
      return Command::SUCCESS;
    }

    $hotReload = !$input->getOption('no-hot-reload');

    $output->writeln(sprintf(
      '<comment>Watching Web Components%s...</comment>',
      $hotReload ? ' with hot reload' : ''
    ));

    $status = $this->watchComponents($builder, $workspace, $hotReload);

    if (in_array($status, self::EXPECTED_SHUTDOWN_EXIT_CODES, true)) {
      return Command::SUCCESS;
    }

    $output->writeln('<error>Failed to watch Web Components. Ensure esbuild is installed and available on PATH.</error>');
    return Command::FAILURE;
  }

  protected function createBuilder(): WebComponentBuilder
  {
    return new WebComponentBuilder();
  }

  protected function watchComponents(WebComponentBuilder $builder, string $workspace, bool $hotReload): int
  {
    $hotReloadState = null;

    if ($hotReload) {
      $hotReloadState = new WebComponentHotReloadState($workspace);

      if (!$hotReloadState->activate()) {
        return Command::FAILURE;
      }

      register_shutdown_function(static function () use ($hotReloadState): void {
        $hotReloadState->deactivate();
      });
    }

    try {
      return $builder->build(
        $workspace,
        true,
        static function () use ($hotReloadState): void {
          $hotReloadState?->synchronize();
        }
      );
    } finally {
      $hotReloadState?->deactivate();
    }
  }
}
