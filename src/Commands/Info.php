<?php

namespace Assegai\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'info',
  description: 'Output information about the application.',
  aliases: ['i']
)]
class Info extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    if ($headerContent = file_get_contents(__DIR__ . '/../../assets/header.txt') )
    {
      $output->writeln("<info>$headerContent</info>");
    }

    return $this->getApplication()->doRun(new ArrayInput([
      'command' => 'help'
    ]), $output);
  }
}