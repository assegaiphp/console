<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Enumerations\Color;
use Assegai\Console\Util\Enumerations\ColorFX;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'update',
  description: 'Updates your application and its dependencies. See https://update.assegaiphp.com/',
  aliases: ['u']
)]
class Update extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $output->writeln(sprintf(
      "%s%s▹▹▹▹▹%s Update in progress... ☕\n",
      Color::FG_LIGHT_BLUE->value,
      ColorFX::BLINK->value,
      Color::RESET->value
    ));

    if (false === passthru("composer update --ansi"))
    {
      $output->writeln('<error>Update error</error>');
      return Command::FAILURE;
    }

    $output->writeln("\n✔️ Update complete! \n");

    return Command::SUCCESS;
  }
}