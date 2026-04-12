<?php

namespace Assegai\Console\Commands\WebComponents;

use Assegai\Console\Util\Inspector;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'wc:list',
  description: 'List discovered Web Components.'
)]
/**
 * Lists the Web Components discovered in an Assegai workspace.
 */
class ListWebComponents extends Command
{
  private const int MINIMUM_TAG_COLUMN_WIDTH = 20;

  /**
   * Configures the supported command-line options.
   *
   * @return void Nothing is returned because Symfony reads the configured state directly from the command instance.
   */
  public function configure(): void
  {
    $this
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd())
      ->addOption('json', null, InputOption::VALUE_NONE, 'Render the result as JSON');
  }

  /**
   * Executes the command and renders the discovered Web Components.
   *
   * @param InputInterface $input Provides the selected workspace directory and output mode options.
   * @param OutputInterface $output Receives the rendered command output.
   * @return int Returns a Symfony command status code indicating success or failure.
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = $input->getOption('directory') ?: (getcwd() ?: '');
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>Invalid workspace.</error>');
      return Command::FAILURE;
    }

    $components = $this->createBuilder()->discover($workspace);

    if ($input->getOption('json')) {
      $output->writeln(json_encode($components, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');
      return Command::SUCCESS;
    }

    if (empty($components)) {
      $output->writeln('<comment>No Web Components found.</comment>');
      return Command::SUCCESS;
    }

    foreach ($this->formatComponents($components) as $line) {
      $output->writeln($line);
    }

    return Command::SUCCESS;
  }

  /**
   * Creates the builder used to discover Web Components inside the target workspace.
   *
   * @return WebComponentBuilder Returns the builder responsible for scanning the workspace.
   */
  protected function createBuilder(): WebComponentBuilder
  {
    return new WebComponentBuilder();
  }

  /**
   * Formats the discovered components into aligned output rows.
   *
   * @param array<int, array{path: string, relativePath: string, tag: string}> $components The discovered component metadata.
   * @return list<string> Returns the rendered output rows ready to be written to the console.
   */
  private function formatComponents(array $components): array
  {
    $tagColumnWidth = $this->getTagColumnWidth($components);

    return array_map(
      fn(array $component): string => $this->formatComponentLine($component, $tagColumnWidth),
      $components,
    );
  }

  /**
   * Determines the width of the tag column.
   *
   * @param array<int, array{path: string, relativePath: string, tag: string}> $components The discovered component metadata.
   * @return int Returns the longest tag width or the minimum presentation width, whichever is greater.
   */
  private function getTagColumnWidth(array $components): int
  {
    $longestTagWidth = max(array_map(
      static fn(array $component): int => strlen($component['tag']),
      $components,
    ));

    return max(self::MINIMUM_TAG_COLUMN_WIDTH, $longestTagWidth);
  }

  /**
   * Formats a single component row for console output.
   *
   * @param array{path: string, relativePath: string, tag: string} $component The discovered component metadata.
   * @param int $tagColumnWidth The width reserved for the component tag column.
   * @return string Returns the aligned, colorized output row.
   */
  private function formatComponentLine(array $component, int $tagColumnWidth): string
  {
    $paddedTag = str_pad($component['tag'], $tagColumnWidth);

    return sprintf('<fg=yellow>%s</>  %s', $paddedTag, $component['relativePath']);
  }
}