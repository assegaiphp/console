<?php

namespace Assegai\Console\Commands\WebComponents;

use Assegai\Console\Util\Inspector;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Assegai\Console\WebComponents\WebComponentConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'wc:build',
  description: 'Build the Web Components bundle.'
)]
class BuildWebComponents extends Command
{
  public function configure(): void
  {
    $this->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd());
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
    $status = $builder->build($workspace);

    if ($status !== Command::SUCCESS) {
      $output->writeln('<error>Failed to build Web Components. Ensure esbuild is installed and available on PATH.</error>');
      return Command::FAILURE;
    }

    if (empty($components)) {
      $output->writeln('<comment>No Web Components found.</comment>');
      return Command::SUCCESS;
    }

    $output->writeln(sprintf(
      'Built <info>%d</info> Web Components into <comment>%s</comment>.',
      count($components),
      WebComponentConfig::getOutputPath($workspace)
    ));

    return Command::SUCCESS;
  }

  protected function createBuilder(): WebComponentBuilder
  {
    return new WebComponentBuilder();
  }
}
