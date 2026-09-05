<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\ApplicationSecretKey;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;

#[AsCommand(
  name: 'key:generate',
  description: 'Generate a new APP_SECRET_KEY in the workspace .env file',
)]
class KeyGenerate extends Command
{
  public function configure(): void
  {
    $this
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The workspace directory', getcwd())
      ->addOption('force', null, InputOption::VALUE_NONE, 'Replace an existing key without confirmation')
      ->setHelp(<<<'HELP'
Generate a cryptographically random 32-byte key, encoded as 64 hexadecimal characters.
If .env is missing, it is created from .env.example. Empty or scaffold-placeholder
keys are initialized immediately. Replacing an existing key requires confirmation
or --force; tokens and encrypted data using the old key may stop working.
The key is written to .env and is never printed. Restart long-running application
processes after changing it. Use the same key across instances of one environment.
HELP);
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: ''));
    if (! (new Inspector($input, $output))->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid Assegai workspace.</error>');
      return Command::FAILURE;
    }

    try {
      $key = ApplicationSecretKey::forWorkspace($workspace);
      if ($key->replacesExistingKey() && ! $input->getOption('force')) {
        $output->writeln('<comment>Replacing APP_SECRET_KEY may invalidate tokens and encrypted data using the current key.</comment>');
        if (! $input->isInteractive()) {
          $output->writeln('<error>APP_SECRET_KEY already exists. Use --force to replace it deliberately.</error>');
          return Command::FAILURE;
        }
        $question = new ConfirmationQuestion('Replace the existing application key? [y/N] ', false);
        if (! (new QuestionHelper())->ask($input, $output, $question)) {
          $output->writeln('Application key unchanged.');
          return Command::SUCCESS;
        }
      }

      $key->generate();
      if ($key->createsEnvironmentFile()) {
        $output->writeln('<info>Created .env from .env.example.</info>');
      }
      $output->writeln('<info>APP_SECRET_KEY generated successfully in .env.</info>');
      return Command::SUCCESS;
    } catch (Throwable $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }
}
