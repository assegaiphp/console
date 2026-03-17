<?php

namespace Assegai\Console\Commands\Api;

use Assegai\Console\Api\WorkspaceApiBridge;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'api:client',
  description: 'Generate an API client from the application metadata.',
)]
class ApiClient extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('language', InputArgument::REQUIRED, 'The client language to generate.')
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory.', getcwd())
      ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output filename.', 'generated/assegai-api-client.ts');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $language = strtolower(trim((string) $input->getArgument('language')));
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid Assegai workspace.</error>');
      return Command::FAILURE;
    }

    if ($language !== 'typescript') {
      $output->writeln('<error>Unsupported client language. Supported values: typescript.</error>');
      return Command::FAILURE;
    }

    try {
      $bridge = new WorkspaceApiBridge($workspace);
      $document = $bridge->generateOpenApiDocument();
      $clientSource = $bridge->generateTypeScriptClient($document);
      $outputFile = $this->resolveOutputFile($workspace, (string) $input->getOption('output'));

      if (!is_dir(dirname($outputFile)) && !mkdir(dirname($outputFile), 0775, true) && !is_dir(dirname($outputFile))) {
        throw new RuntimeException('Failed to create the output directory.');
      }

      if (false === file_put_contents($outputFile, $clientSource)) {
        throw new RuntimeException('Failed to write the generated client.');
      }

      $output->writeln('<info>GENERATED</info> ' . $outputFile);

      return Command::SUCCESS;
    } catch (RuntimeException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

  private function resolveOutputFile(string $workspace, string $output): string
  {
    if (str_starts_with($output, '/')) {
      return Path::normalize($output);
    }

    return Path::join($workspace, $output);
  }
}
