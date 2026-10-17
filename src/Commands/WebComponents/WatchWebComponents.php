<?php

namespace Assegai\Console\Commands\WebComponents;

use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Assegai\Console\WebComponents\HotReload\WebComponentHotReloadState;
use Assegai\Console\WebComponents\WebComponentGroup;
use Assegai\Console\WebComponents\WebComponentConfig;
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
  /** @var list<array{group: WebComponentGroup, components: array<int, array{path: string, relativePath: string, tag: string, group?: string}>}> */
  private array $plan = [];
  /** @var array<int, WebComponentHotReloadState> */
  private array $hotReloadStates = [];
  private bool $watchActive = false;

  public function configure(): void
  {
    $this
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd())
      ->addOption('group', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Watch only these configured groups')
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
    try {
      $this->plan = $builder->prepare($workspace, $input->getOption('group'));
    } catch (\InvalidArgumentException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }

    $hotReload = !$input->getOption('no-hot-reload');

    $output->writeln(sprintf(
      '<comment>Watching Web Components%s...</comment>',
      $hotReload ? ' with hot reload' : ''
    ));

    try {
      $status = $this->watchComponents($builder, $workspace, $hotReload);
    } catch (\InvalidArgumentException | \RuntimeException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }

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
    $cache = WebComponentConfig::getCacheDirectory($workspace);
    if (!is_dir($cache) && !mkdir($cache, 0755, true)) {
      return Command::FAILURE;
    }
    $lock = fopen(Path::join($cache, 'wc-watch.lock'), 'c');
    if ($lock === false) {
      throw new \RuntimeException('Cannot open Web Component watch lock.');
    }
    $states = [];
    register_shutdown_function(function (): void {
      if ($this->watchActive) {
        foreach ($this->hotReloadStates as $state) {
          $state->deactivate();
        }
      }
    });
    try {
      if (!flock($lock, LOCK_EX | LOCK_NB)) {
        throw new \RuntimeException('A Web Component watch session is already running in this workspace.');
      }
      if ($hotReload) {
        $states[-1] = new WebComponentHotReloadState($workspace);
        foreach ($this->plan as $number => $item) {
          if ($item['group']->name !== null) {
            $states[$number] = new WebComponentHotReloadState($workspace, $item['group']);
          }
        }
        $this->hotReloadStates = $states;
        $this->watchActive = true;
        foreach ($states as $state) {
          if (!$state->activate()) {
            return Command::FAILURE;
          }
        }
      }
      $versions = [];
      $onTick = $states === [] ? null : static function (array $completed) use ($states, &$versions): void {
        $versions = array_replace($versions, $completed);
        foreach ($states as $number => $state) {
          $version = $number === -1 && $versions !== [] ? sha1(serialize($versions)) : ($versions[$number] ?? null);
          $state->synchronize(is_string($version) ? $version : null, false);
        }
      };
      return $builder->build(
        $workspace,
        true,
        $onTick,
        $this->plan
      );
    } finally {
      foreach ($states as $state) {
        $state->deactivate();
      }
      $this->watchActive = false;
      fclose($lock);
    }
  }
}
