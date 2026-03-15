<?php

namespace Assegai\Console\Commands\WebComponents;

use Assegai\Console\Util\Inspector;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'wc:list',
  description: 'List discovered Web Components.'
)]
class ListWebComponents extends Command
{
  public function configure(): void
  {
    $this
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd())
      ->addOption('json', null, InputOption::VALUE_NONE, 'Render the result as JSON');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = $input->getOption('directory') ?: (getcwd() ?: '');
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>Invalid workspace.</error>');
      return Command::FAILURE;
    }

    $components = $this->createBuilder()->discover($workspace);

    if ($input->getOption('json')) {
      $output->writeln(json_encode($components, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');
      return Command::SUCCESS;
    }

    if (empty($components)) {
      $output->writeln('<comment>No Web Components found.</comment>');
      return Command::SUCCESS;
    }

    foreach ($components as $component) {
      $output->writeln(sprintf('%s  %s', $component['tag'], $component['relativePath']));
    }

    return Command::SUCCESS;
  }

  protected function createBuilder(): WebComponentBuilder
  {
    return new WebComponentBuilder();
  }
}
