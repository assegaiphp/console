<?php

namespace Assegai\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'dump:autoload',
  description: 'Dumps the autoloader',
  aliases: ['da'])
]
class DumpAutoload extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $dumpAutoloadResult = passthru('composer dump-autoload --ansi');

    if (false === $dumpAutoloadResult)
    {
      $output->writeln('<error>Failed to dump the autoloader</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }
}