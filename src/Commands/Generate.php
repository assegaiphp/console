<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'generate',
  description: 'Generates an assegai element',
  aliases: ['g']
)]
class Generate extends Command
{
  /**
   * @var array<SchematicInterface> $schematics
   */
  protected array $schematics = [];

  public function configure(): void
  {
    $this
      ->addArgument('schematic', InputArgument::REQUIRED, 'The schematic to generate')
      ->setHelp(implode("\n", [
        "Available schematics:",
        "    ┌───────────────┬─────────────┬──────────────────────────────────────────────┐",
        "    │ <fg=red>Schematic</>     │ <fg=red>Alias</>       │ <fg=red>Description</>                                  │",
        "    ├───────────────┼─────────────┼──────────────────────────────────────────────┤",
        "    │ <fg=green>application</>   │ <comment>application</comment> │ Generate a new application workspace         │",
        "    │ <fg=green>controller</>    │ <comment>c</comment>           │ Generate a controller declaration            │",
        "    │ <fg=green>class</>         │ <comment>cl</comment>          │ Generate a new class                         │",
        "    │ <fg=green>guard</>         │ <comment>g</comment>           │ Generate a guard declaration                 │",
        "    │ <fg=green>interface</>     │ <comment>i</comment>           │ Generate an interface                        │",
        "    │ <fg=green>module</>        │ <comment>m</comment>           │ Generate a module declaration                │",
        "    │ <fg=green>pipe</>          │ <comment>p</comment>           │ Generate a pipe declaration                  │",
        "    │ <fg=green>resource</>      │ <comment>r</comment>           │ Generate a new CRUD resource                 │",
        "    │ <fg=green>service</>       │ <comment>s</comment>           │ Generate a service declaration               │",
        "    └───────────────┴─────────────┴──────────────────────────────────────────────┘"
      ]));
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement execute() method.

    return Command::SUCCESS;
  }
}