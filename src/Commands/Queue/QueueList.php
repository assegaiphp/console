<?php

namespace Assegai\Console\Commands\Queue;

use Assegai\Console\Queue\WorkspaceQueueBridge;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'queue:list',
  description: 'List configured queue connections and discovered processors.',
  aliases: ['q:l'],
)]
class QueueList extends Command
{
  public function configure(): void
  {
    $this->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory.', getcwd());
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid Assegai workspace.</error>');
      return Command::FAILURE;
    }

    try {
      $queues = $this->newQueueBridge($workspace)->listQueues();

      if ($queues === []) {
        $output->writeln('<comment>No queue connections were found in config/queues.php.</comment>');
        return Command::SUCCESS;
      }

      $table = new Table($output);
      $table->setHeaders(['Connection', 'Driver', 'Queue', 'Processor']);

      foreach ($queues as $queue) {
        $table->addRow([
          $queue['path'],
          $queue['driver'],
          $queue['name'],
          $queue['processors'] === [] ? 'None discovered' : implode(PHP_EOL, $queue['processors']),
        ]);
      }

      $table->render();

      return Command::SUCCESS;
    } catch (RuntimeException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

  protected function newQueueBridge(string $workspace): WorkspaceQueueBridge
  {
    return new WorkspaceQueueBridge($workspace);
  }
}
