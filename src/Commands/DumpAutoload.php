<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Inspector;
use Assegai\Console\WebComponents\Builder\WebComponentBuilder;
use Assegai\Console\WebComponents\WebComponentConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'dump-autoload',
  description: 'Dumps the autoloader',
  aliases: ['da'])
]
class DumpAutoload extends Command
{
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $inspector = new Inspector($input, $output);

    $workspace = getcwd();
    if ($inspector->isValidWorkspace(is_bool($workspace) ? '' : $workspace) === false)
    {
      $output->writeln('<error>Not a valid workspace</error>');
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->runComposerDumpAutoload()) {
      $output->writeln('<error>Failed to dump the autoloader</error>');
      return Command::FAILURE;
    }

    if ($this->shouldBuildWebComponents($workspace)) {
      $output->writeln('<comment>Building Web Components...</comment>');

      if (Command::SUCCESS !== $this->buildWebComponents($workspace)) {
        $output->writeln('<error>Failed to build Web Components</error>');
        return Command::FAILURE;
      }
    }

    return Command::SUCCESS;
  }

  protected function runComposerDumpAutoload(): int
  {
    passthru('composer dump-autoload --ansi', $statusCode);

    return $statusCode;
  }

  protected function shouldBuildWebComponents(string $workspace): bool
  {
    return WebComponentConfig::shouldBuildOnDumpAutoload($workspace);
  }

  protected function buildWebComponents(string $workspace): int
  {
    return (new WebComponentBuilder())->build($workspace);
  }
}
