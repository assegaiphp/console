<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'version',
  description: 'Output the current version.',
  aliases: ['v']
)]
class Version extends Command
{
  public function configure(): void
  {
    $this->addOption('directory', 'd', InputOption::VALUE_OPTIONAL, 'The directory to inspect.', getcwd());
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);
    $workspace = $input->getOption('directory');

    if ( ! $inspector->isValidWorkspace($workspace) )
    {
      return Command::FAILURE;
    }

    $version = `composer global show sendamaphp/console | grep 'versions'`;
    $version = preg_replace('/versions\s*:\s*\*\s*(.*)/', '$1', $version);
    $output->writeln("<info>$version</info>");

    return Command::SUCCESS;
  }
}