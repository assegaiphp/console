<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'dump-autoload',
  description: 'Dumps the autoloader',
  aliases: ['da'])
]
class DumpAutoload extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);

    $workspace = getcwd();
    if ($inspector->isValidWorkspace(is_bool($workspace) ? '' : $workspace) === false)
    {
      $output->writeln('<error>Not a valid workspace</error>');
      return Command::FAILURE;
    }

    $dumpAutoloadResult = passthru('composer dump-autoload --ansi');

    if (false === $dumpAutoloadResult)
    {
      $output->writeln('<error>Failed to dump the autoloader</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}