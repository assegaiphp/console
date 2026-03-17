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
  name: 'api:export',
  description: 'Export generated API metadata into other formats.',
)]
class ApiExport extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('format', InputArgument::REQUIRED, 'The export format. Supported values: openapi, postman.')
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory.', getcwd())
      ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output filename.', null);
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $format = strtolower(trim((string) $input->getArgument('format')));
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid Assegai workspace.</error>');
      return Command::FAILURE;
    }

    if (!in_array($format, ['openapi', 'postman'], true)) {
      $output->writeln('<error>Unsupported export format. Supported values: openapi, postman.</error>');
      return Command::FAILURE;
    }

    try {
      $bridge = new WorkspaceApiBridge($workspace);
      $document = $bridge->generateOpenApiDocument();
      $payload = $format === 'postman'
        ? $bridge->generatePostmanCollection($document)
        : $document;
      $outputFile = $this->resolveOutputFile(
        $workspace,
        (string) ($input->getOption('output') ?: $this->defaultOutputFor($format))
      );

      if (!is_dir(dirname($outputFile)) && !mkdir(dirname($outputFile), 0775, true) && !is_dir(dirname($outputFile))) {
        throw new RuntimeException('Failed to create the output directory.');
      }

      $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

      if (false === file_put_contents($outputFile, $encoded)) {
        throw new RuntimeException('Failed to write the exported API artifact.');
      }

      $output->writeln('<info>GENERATED</info> ' . $outputFile);

      return Command::SUCCESS;
    } catch (RuntimeException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

  private function defaultOutputFor(string $format): string
  {
    return match ($format) {
      'postman' => 'generated/assegai.postman.collection.json',
      default => 'generated/openapi.json',
    };
  }

  private function resolveOutputFile(string $workspace, string $output): string
  {
    if (str_starts_with($output, '/')) {
      return Path::normalize($output);
    }

    return Path::join($workspace, $output);
  }
}
