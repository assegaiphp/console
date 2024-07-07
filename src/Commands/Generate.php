<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\ApplicationSchematic;
use Assegai\Console\Core\Schematics\ClassSchematic;
use Assegai\Console\Core\Schematics\ComponentSchematic;
use Assegai\Console\Core\Schematics\ControllerSchematic;
use Assegai\Console\Core\Schematics\EnumSchematic;
use Assegai\Console\Core\Schematics\GuardSchematic;
use Assegai\Console\Core\Schematics\InterceptorSchematic;
use Assegai\Console\Core\Schematics\InterfaceSchematic;
use Assegai\Console\Core\Schematics\ModuleSchematic;
use Assegai\Console\Core\Schematics\PipeSchematic;
use Assegai\Console\Core\Schematics\ResourceSchematic;
use Assegai\Console\Core\Schematics\ServiceSchematic;
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
  /**
   * @var array<string, string> $aliasMap The alias map
   */
  protected array $aliasMap = [
    'application' => 'application',
    'cm'          => 'component',
    'c'           => 'controller',
    'cl'          => 'class',
    'g'           => 'guard',
    'ic'          => 'interceptor',
    'i'           => 'interface',
    'm'           => 'module',
    'p'           => 'pipe',
    'r'           => 'resource',
    's'           => 'service'
  ];

  /**
   * @var SchematicInterface|null $schematic The schematic to generate
   */
  protected ?SchematicInterface $schematic = null;

  public function configure(): void
  {
    $this
      ->addArgument(
        'schematic',
        InputArgument::REQUIRED,
        'The schematic to generate',
        null,
        array_values($this->aliasMap)
      )
      ->addArgument('name', InputArgument::REQUIRED, 'The name of the schematic to generate')
      ->addOption('directory', 'd', InputArgument::OPTIONAL, 'The directory to generate the schematic in', getcwd())
      ->setHelp(implode("\n", [
        "Available schematics:",
        "    ┌───────────────┬─────────────┬──────────────────────────────────────────────┐",
        "    │ <fg=blue>Schematic</>     │ <fg=blue>Alias</>       │ <fg=blue>Description</>                                  │",
        "    ├───────────────┼─────────────┼──────────────────────────────────────────────┤",
        "    │ <fg=green>application</>   │ <comment>application</comment> │ Generate a new application workspace         │",
        "    │ <fg=green>component</>     │ <comment>cm</comment>          │ Generate a component declaration            │",
        "    │ <fg=green>controller</>    │ <comment>c</comment>           │ Generate a controller declaration            │",
        "    │ <fg=green>class</>         │ <comment>cl</comment>          │ Generate a new class                         │",
        "    │ <fg=green>guard</>         │ <comment>g</comment>           │ Generate a guard declaration                 │",
        "    │ <fg=green>interceptor</>   │ <comment>ic</comment>          │ Generate an interceptor                      │",
        "    │ <fg=green>interface</>     │ <comment>i</comment>           │ Generate an interface                        │",
        "    │ <fg=green>module</>        │ <comment>m</comment>           │ Generate a module declaration                │",
        "    │ <fg=green>pipe</>          │ <comment>p</comment>           │ Generate a pipe declaration                  │",
        "    │ <fg=green>resource</>      │ <comment>r</comment>           │ Generate a new CRUD resource                 │",
        "    │ <fg=green>service</>       │ <comment>s</comment>           │ Generate a service declaration               │",
        "    └───────────────┴─────────────┴──────────────────────────────────────────────┘"
      ]));
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $name = basename($input->getArgument('name'));
    $directory = $input->getOption('directory');

    if (false === $directory) {
      $output->writeln('<error>Invalid working directory</error>');
      return Command::FAILURE;
    }

    $subdirectory = dirname($input->getArgument('name')) ?: '';

    $this->addAllSchematics([
      'application' => new ApplicationSchematic($input, $output, $name, $directory, $subdirectory),
      'component'   => new ComponentSchematic($input, $output, $name, $directory, $subdirectory),
      'controller'  => new ControllerSchematic($input, $output, $name, $directory, $subdirectory),
      'class'       => new ClassSchematic($input, $output, $name, $directory, $subdirectory),
      'enum'        => new EnumSchematic($input, $output, $name, $directory, $subdirectory),
      'guard'       => new GuardSchematic($input, $output, $name, $directory, $subdirectory),
      'interceptor' => new InterceptorSchematic($input, $output, $name, $directory, $subdirectory),
      'interface'   => new InterfaceSchematic($input, $output, $name, $directory, $subdirectory),
      'module'      => new ModuleSchematic($input, $output, $name, $directory, $subdirectory),
      'pipe'        => new PipeSchematic($input, $output, $name, $directory, $subdirectory),
      'resource'    => new ResourceSchematic($input, $output, $name, $directory, $subdirectory),
      'service'     => new ServiceSchematic($input, $output, $name, $directory, $subdirectory)
    ]);

    if ($this->setSchematic($input->getArgument('schematic')) !== Command::SUCCESS) {
      $output->writeln("<error>Invalid schematic</error>");
      return Command::FAILURE;
    }

    if ( Command::SUCCESS !== $this->schematic?->prepareBuild() ) {
      $output->writeln("<error>Failed to prepare the build</error>");
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->schematic?->build() ) {
      $output->writeln("<error>Failed to build the schematic</error>");
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->schematic?->finalizeBuild() ) {
      $output->writeln("<error>Failed to finalize the build</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Add a schematic to the command
   *
   * @param string $schematicName The name of the schematic
   * @param SchematicInterface $schematic The schematic to add
   * @return self
   */
  public function addSchematic(string $schematicName, SchematicInterface $schematic): self
  {
    if (! key_exists($schematicName, $this->schematics) && ! in_array($schematic, $this->schematics) ) {
      $this->schematics[$schematicName] = $schematic;
    }

    return $this;
  }

  /**
   * Add all schematics to the command
   *
   * @param array<SchematicInterface> $schematics
   * @return self
   */
  public function addAllSchematics(array $schematics): self
  {
    foreach ($schematics as $schematicName => $schematic) {
      $this->addSchematic($schematicName, $schematic);
    }
    return $this;
  }

  /**
   * Set the schematic to generate
   *
   * @param string $schematicName The name of the schematic to generate
   * @return int The status of the operation
   */
  private function setSchematic(string $schematicName): int
  {
    $schematicName = $this->aliasMap[$schematicName] ?? $schematicName;

    if (! key_exists($schematicName, $this->schematics)) {
      return Command::FAILURE;
    }

    $this->schematic = $this->schematics[$schematicName];

    return Command::SUCCESS;
  }
}