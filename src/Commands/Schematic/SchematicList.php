<?php

namespace Assegai\Console\Commands\Schematic;

use Assegai\Console\Core\Schematics\Registry\SchematicRegistryFactory;
use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'schematic:list',
  description: 'List discovered built-in, local, and package schematics.'
)]
class SchematicList extends Command
{
  public function configure(): void
  {
    $this->addOption(
      'directory',
      'd',
      InputOption::VALUE_REQUIRED,
      'The workspace directory used for schematic discovery.',
      getcwd() ?: '.',
    );
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $directory = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: '.'));

    try {
      $registry = SchematicRegistryFactory::build($input, $output, $directory);
    } catch (RuntimeException $exception) {
      $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
      return Command::FAILURE;
    }

    $table = new Table($output);
    $table->setHeaders(['Name', 'Aliases', 'Source', 'Origin', 'Description']);

    foreach ($registry->all() as $definition) {
      $table->addRow([
        $definition->name,
        $definition->aliases === [] ? '-' : implode(', ', $definition->aliases),
        $definition->sourceType,
        $definition->source,
        $definition->description,
      ]);
    }

    $table->render();

    return Command::SUCCESS;
  }
}
