<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Inspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'config',
    description: 'Retrieves or sets Assegai configuration values in the assegai.json file for the workspace.'
)]
class Config extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument(
        'json-path',
        InputArgument::REQUIRED,
        'The configuration key to set or query, in JSON path format. For example: "a[3].foo.bar[2]". ' .
        'If no new value is provided, returns the current value of this key.')
      ->addArgument(
        'value',
        InputArgument::OPTIONAL,
        'The new value to set for the configuration key. If not provided, the current value of the ' .
        'key is returned.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);
    $path = $input->getArgument('json-path');
    $workingDirectory = getcwd() ?: '';

    if (! $path )
    {
      $output->writeln('<error>Invalid JSON path provided.</error>');
      return Command::FAILURE;
    }

    if (! $inspector->isValidWorkspace($workingDirectory) )
    {
      $output->writeln('<error>Invalid workspace.</error>');
      return Command::FAILURE;
    }

    $projectConfig = new ProjectConfig($input, $output, $workingDirectory);
    $projectConfig->load();

    if ($value = $input->getArgument('value')) {
      if (is_scalar($value)) {
        if (!is_string($value)) {
          $value = (string) $value;
        }
        $value = match(true) {
          is_numeric($value) => preg_match('/\d+.\d+/', $value) ? (float) $value : (int) $value,
          false !== preg_match('/(true|false)/', $value) => boolval($value),
          default => $value
        };
      }

      // Set the value
      $projectConfig->set($path, $value);

      if (Command::SUCCESS !== $projectConfig->commit()) {
        $output->writeln('<error>Failed to commit the configuration changes.</error>');
        return Command::FAILURE;
      }

      $output->writeln("Set <comment>$path</comment> to <info>$value</info> in the project configuration.");
      return Command::SUCCESS;
    }

    // Get the value
    $value = $projectConfig->get($path);

    if (is_scalar($value))
    {
      $output->writeln(match(true) {
        is_bool($value) => $value ? 'true' : 'false',
        default => (string) $value
      });
    }

    if (is_array($value))
    {
      $output->writeln(json_encode($value, JSON_PRETTY_PRINT) ?: '');
    }

    return Command::SUCCESS;
  }
}