<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Registry\SchematicContext;
use Assegai\Console\Core\Schematics\Registry\SchematicDefinition;
use Assegai\Console\Core\Schematics\Registry\SchematicRegistry;
use Assegai\Console\Core\Schematics\Registry\SchematicRegistryFactory;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'generate',
  description: 'Generates an Assegai element',
  aliases: ['g']
)]
class Generate extends Command
{
  protected ?SchematicRegistry $registry = null;
  protected ?SchematicDefinition $selectedDefinition = null;
  protected ?SchematicInterface $schematic = null;
  protected string $workingDirectory = '.';

  public function configure(): void
  {
    $this->setDefinition($this->createBaseDefinition());
    $this->setHelp($this->buildGenericHelp());
  }

  public function run(InputInterface $input, OutputInterface $output): int
  {
    $this->schematic = null;
    $this->selectedDefinition = null;
    $this->registry = null;

    $this->setDefinition($this->createBaseDefinition());
    $this->mergeApplicationDefinition();

    try {
      $input->bind($this->getDefinition());
    } catch (ExceptionInterface) {
      // The lightweight first pass only exists to pick up the schematic and directory.
      // Unknown schematic-specific options are re-bound once the selected schematic
      // has contributed its full input definition.
    }

    $this->workingDirectory = $this->resolveDirectory($input);

    try {
      $this->registry = SchematicRegistryFactory::build($input, $output, $this->workingDirectory);
    } catch (RuntimeException $exception) {
      $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
      return Command::FAILURE;
    }

    $this->setHelp($this->buildGenericHelp());

    $requestedSchematic = $this->resolveRequestedSchematic($input);

    if ($requestedSchematic !== null && $this->registry !== null) {
      $selectedDefinition = $this->registry->get($requestedSchematic);
      $this->selectedDefinition = $selectedDefinition;

      if ($selectedDefinition !== null) {
        $this->setDefinition($this->buildDefinitionFor($selectedDefinition));
        $this->mergeApplicationDefinition();
        $input->bind($this->getDefinition());
        $this->setHelp($this->buildSchematicHelp($selectedDefinition));
      }
    }

    $this->initialize($input, $output);

    if ($input->isInteractive()) {
      $this->interact($input, $output);
    }

    if ($input->hasArgument('command') && null === $input->getArgument('command')) {
      $input->setArgument('command', $this->getName());
    }

    $input->validate();

    return $this->execute($input, $output);
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $requestedSchematic = $this->resolveRequestedSchematic($input);

    if ($requestedSchematic === null || $requestedSchematic === '') {
      $output->writeln('<error>You must specify a schematic.</error>');
      return Command::FAILURE;
    }

    $definition = $this->selectedDefinition;

    if ($definition === null && $this->registry !== null) {
      $definition = $this->registry->get($requestedSchematic);
    }

    if ($definition === null) {
      $output->writeln(sprintf('<error>Invalid schematic: %s</error>', $requestedSchematic));
      return Command::FAILURE;
    }

    if ($definition->requiresWorkspace) {
      $inspector = new Inspector($input, $output);

      if (! $inspector->isValidWorkspace($this->workingDirectory)) {
        $output->writeln('<error>This is not a valid Assegai workspace.</error>');
        return Command::FAILURE;
      }
    }

    $context = new SchematicContext(
      input: $input,
      output: $output,
      definition: $definition,
      directory: $this->workingDirectory,
      requestedName: $this->resolveRequestedName($definition, $input),
    );

    try {
      $this->schematic = $definition->createSchematic($context);
    } catch (RuntimeException $exception) {
      $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->schematic->prepareBuild()) {
      $output->writeln('<error>Failed to prepare the build</error>');
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->schematic->build()) {
      $output->writeln('<error>Failed to build the schematic</error>');
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->schematic->finalizeBuild()) {
      $output->writeln('<error>Failed to finalize the build</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  public function getHelp(): string
  {
    if ($this->registry !== null) {
      if ($this->selectedDefinition !== null) {
        return $this->buildSchematicHelp($this->selectedDefinition);
      }

      return $this->buildGenericHelp();
    }

    try {
      $argv = $_SERVER['argv'] ?? [$_SERVER['PHP_SELF'] ?? 'assegai'];
      $input = new ArgvInput($argv);
      $directory = Path::normalize(getcwd() ?: '.');
      $registry = SchematicRegistryFactory::build($input, new NullOutput(), $directory);
      $selected = $registry->get($this->resolveRequestedSchematicFromArgv($argv) ?? '');

      return $selected !== null
        ? $this->buildSchematicHelp($selected)
        : $this->buildGenericHelpFromRegistry($registry);
    } catch (\Throwable) {
      return parent::getHelp();
    }
  }

  private function createBaseDefinition(): InputDefinition
  {
    return new InputDefinition([
      new InputArgument('schematic', InputArgument::OPTIONAL, 'The schematic to generate.'),
      new InputArgument('name', InputArgument::OPTIONAL, 'The name of the schematic to generate.'),
      new InputOption(
        'directory',
        'd',
        InputOption::VALUE_REQUIRED,
        'The directory to generate the schematic in.',
        getcwd() ?: '.',
      ),
      new InputOption(
        'queue',
        null,
        InputOption::VALUE_REQUIRED,
        'The queue connection path used by generated queue processors.',
        'driver.connection',
      ),
      new InputOption(
        'job',
        null,
        InputOption::VALUE_REQUIRED,
        'The job class used to type generated queue processor methods.',
        null,
      ),
      new InputOption(
        'wc',
        null,
        InputOption::VALUE_NONE,
        'Generate or pair a Web Component runtime file where supported.',
      ),
    ]);
  }

  private function buildDefinitionFor(SchematicDefinition $definition): InputDefinition
  {
    $arguments = [
      new InputArgument('schematic', InputArgument::REQUIRED, 'The schematic to generate.'),
    ];

    foreach ($definition->arguments as $argument) {
      $arguments[] = $argument->toInputArgument();
    }

    $inputDefinition = new InputDefinition($arguments);
    $inputDefinition->addOption(new InputOption(
      'directory',
      'd',
      InputOption::VALUE_REQUIRED,
      'The directory to generate the schematic in.',
      getcwd() ?: '.',
    ));

    foreach ($definition->options as $option) {
      $inputDefinition->addOption($option->toInputOption());
    }

    return $inputDefinition;
  }

  private function resolveDirectory(InputInterface $input): string
  {
    $directory = $input->getParameterOption(['--directory', '-d'], getcwd() ?: '.');

    if (! is_string($directory) || trim($directory) === '') {
      return '.';
    }

    return Path::normalize($directory);
  }

  private function resolveRequestedSchematic(InputInterface $input): ?string
  {
    $value = $input->getArgument('schematic');

    if (is_string($value) && trim($value) !== '') {
      return trim($value);
    }

    $firstArgument = $input->getFirstArgument();

    if (! is_string($firstArgument) || trim($firstArgument) === '') {
      return null;
    }

    return trim($firstArgument);
  }

  /**
   * @param array<int, string> $argv
   */
  private function resolveRequestedSchematicFromArgv(array $argv): ?string
  {
    $commandTokenSkipped = false;

    foreach (array_slice($argv, 1) as $token) {
      if (str_starts_with($token, '-')) {
        continue;
      }

      if (! $commandTokenSkipped && ($token === $this->getName() || in_array($token, $this->getAliases(), true))) {
        $commandTokenSkipped = true;
        continue;
      }

      return trim($token);
    }

    return null;
  }

  private function resolveRequestedName(SchematicDefinition $definition, InputInterface $input): string
  {
    foreach ($definition->arguments as $argument) {
      if ($argument->name === 'name' && $input->hasArgument('name')) {
        $value = $input->getArgument('name');
        return is_string($value) ? $value : (string) $value;
      }
    }

    foreach ($definition->arguments as $argument) {
      if (! $input->hasArgument($argument->name)) {
        continue;
      }

      $value = $input->getArgument($argument->name);

      return is_string($value) ? $value : (string) $value;
    }

    return '';
  }

  private function buildGenericHelp(): string
  {
    if ($this->registry === null) {
      return "Available schematics are resolved from built-ins, workspace manifests, and installed packages.\n\nUse <info>assegai schematic:list</info> to inspect discovered schematics.";
    }

    return $this->buildGenericHelpFromRegistry($this->registry);
  }

  private function buildGenericHelpFromRegistry(SchematicRegistry $registry): string
  {
    $lines = [
      'Available schematics:',
    ];

    foreach ($registry->all() as $definition) {
      $aliases = $definition->aliases === [] ? '-' : implode(', ', $definition->aliases);
      $lines[] = sprintf(
        '  <info>%-18s</info> <comment>%-12s</comment> %s',
        $definition->name,
        $aliases,
        $definition->description
      );
    }

    $lines[] = '';
    $lines[] = 'Use <info>assegai g <schematic> --help</info> to inspect schematic-specific arguments and options.';

    return implode(PHP_EOL, $lines);
  }

  private function buildSchematicHelp(SchematicDefinition $definition): string
  {
    $lines = [
      sprintf('%s', $definition->description),
      '',
      sprintf('Source: %s (%s)', $definition->source, $definition->sourceType),
    ];

    if ($definition->aliases !== []) {
      $lines[] = sprintf('Aliases: %s', implode(', ', $definition->aliases));
    }

    $lines[] = '';
    $lines[] = 'Example:';
    $lines[] = sprintf('  <info>assegai g %s</info> ...', $definition->name);

    return implode(PHP_EOL, $lines);
  }
}
