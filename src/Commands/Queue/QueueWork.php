<?php

namespace Assegai\Console\Commands\Queue;

use Assegai\Console\Queue\WorkspaceQueueBridge;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
  name: 'queue:work',
  description: 'Work a configured queue connection with a discovered processor.',
  aliases: ['queue:listen', 'q:w'],
)]
class QueueWork extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('connection', InputArgument::OPTIONAL, 'The queue connection in driver.connection form, for example beanstalk.notifications.')
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory.', getcwd())
      ->addOption('processor', null, InputOption::VALUE_REQUIRED, 'The processor class to use instead of auto-discovery.', null)
      ->addOption('once', null, InputOption::VALUE_NONE, 'Process at most one available job and exit.')
      ->addOption('max-jobs', null, InputOption::VALUE_REQUIRED, 'Stop after the given number of processed jobs.', '0')
      ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Milliseconds to sleep when the queue is empty.', '500')
      ->addOption('stop-when-empty', null, InputOption::VALUE_NONE, 'Exit when no job is available instead of waiting for more work.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    $inspector = new Inspector($input, $output);

    if (!$inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid Assegai workspace.</error>');
      return Command::FAILURE;
    }

    $connection = $input->getArgument('connection');
    $processor = $input->getOption('processor');
    $sleepMilliseconds = max(0, (int) ($input->getOption('sleep') ?: 500));
    $maxJobs = max(0, (int) ($input->getOption('max-jobs') ?: 0));
    $once = (bool) $input->getOption('once');
    $stopWhenEmpty = (bool) $input->getOption('stop-when-empty');

    try {
      $bridge = $this->newQueueBridge($workspace);
      $output->writeln('<info>QUEUE WORKER</info> Bootstrapping queue worker...');

      $result = $bridge->work(
        connectionPath: is_string($connection) ? $connection : null,
        processorClass: is_string($processor) ? $processor : null,
        sleepMilliseconds: $sleepMilliseconds,
        once: $once,
        maxJobs: $maxJobs,
        stopWhenEmpty: $stopWhenEmpty,
        onProcessed: function (object $job, array $definition, array $processor) use ($output): void {
          $output->writeln(sprintf(
            '<info>PROCESSED</info> %s via %s::%s',
            $definition['path'],
            $processor['class'],
            $processor['method']
          ), OutputInterface::VERBOSITY_VERBOSE);
          $output->writeln(sprintf('Processed job: <comment>%s</comment>', $job::class));
        },
        onError: function (Throwable $error, object $job, array $definition, array $processor) use ($output): void {
          $output->writeln(sprintf(
            '<error>Failed processing %s with %s::%s: %s</error>',
            $definition['path'],
            $processor['class'],
            $processor['method'],
            $error->getMessage()
          ));
          $output->writeln('Job class: ' . $job::class, OutputInterface::VERBOSITY_VERBOSE);
        },
      );

      $output->writeln(sprintf(
        '<info>WORKER READY</info> %s::%s',
        $result['processorClass'],
        $result['processorMethod']
      ), OutputInterface::VERBOSITY_VERBOSE);
      $output->writeln(sprintf(
        '<info>DONE</info> Processed %d job%s.',
        $result['processedJobs'],
        $result['processedJobs'] === 1 ? '' : 's'
      ));

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
