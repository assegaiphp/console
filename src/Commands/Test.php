<?php

namespace Assegai\Console\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'test',
  description: 'Run unit tests in a project.',
  aliases: ['t'],
)]
class Test extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this->setHelp("This command runs unit tests in a project. It is an alias for the assegai test runner. Once executed, it will run all tests in the project.");
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $result = $this->runComposerTests();

    if ($result['stdout'] !== '') {
      $output->write($result['stdout'], false, OutputInterface::OUTPUT_RAW);
    }

    if ($result['status'] !== Command::SUCCESS) {
      if ($result['stderr'] !== '') {
        $output->write($result['stderr'], false, OutputInterface::OUTPUT_RAW);
      }

      $output->writeln('<error>Tests failed.</error>');
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * @return array{status: int, stdout: string, stderr: string}
   */
  protected function runComposerTests(): array
  {
    $descriptors = [
      0 => ['file', 'php://stdin', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $process = proc_open(
      ['composer', 'test', '--ansi'],
      $descriptors,
      $pipes,
      getcwd() ?: null
    );

    if (!is_resource($process)) {
      return [
        'status' => Command::FAILURE,
        'stdout' => '',
        'stderr' => 'Failed to start the Composer test process.' . PHP_EOL,
      ];
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $status = proc_close($process);

    return [
      'status' => $status,
      'stdout' => $stdout === false ? '' : $stdout,
      'stderr' => $stderr === false ? '' : $stderr,
    ];
  }
}
