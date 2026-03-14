<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\WorkspaceManager;
use Assegai\Console\Util\TermInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'new',
  description: 'Creates a new project',
  aliases: ['n']
)]
class NewProject extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the project');
    $this
      ->addOption('directory', 'd', InputArgument::OPTIONAL | InputOption::VALUE_REQUIRED, 'The path to create the project in', getcwd())
      ->addOption('skip-git', 'g', InputOption::VALUE_NONE, 'Skip git repository initialization');
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $projectName = $input->getArgument('name');
    $projectDirectory = $input->getOption('directory');

    /** @var FormatterHelper $formatter */
    $formatter = $this->getHelper('formatter');
    /** @var QuestionHelper $questionHelper */
    $questionHelper = $this->getHelper('question');
    $workspaceManager = new WorkspaceManager($input, $output, $formatter, $questionHelper);

    if ($errorCode = $workspaceManager->init($projectName, $projectDirectory)) {
      $output->writeln($formatter->formatBlock('Failed to create project', 'error', true));
      return $errorCode;
    }

    $projectPath = $workspaceManager->getProjectPath() ?? '';

    $output->writeln('');
    if ($errorCode = $workspaceManager->install()) {
      $output->writeln($formatter->formatBlock('Failed to install project', 'error', true));
      return $errorCode;
    }

    $output->writeln('');
    $this->printSuccessMessage($output, $projectPath);
    return Command::SUCCESS;
  }

  /**
   * Print the success message.
   *
   * @param OutputInterface $output The output interface.
   * @param string $projectPath The created project path.
   */
  private function printSuccessMessage(OutputInterface $output, string $projectPath): void
  {
    $cursor = new Cursor($output);
    $cursor
      ->moveUp()
      ->clearLine();

    $displayPath = $projectPath;
    $workingDirectory = getcwd() ?: '';

    if ($workingDirectory && dirname($projectPath) === $workingDirectory) {
      $displayPath = basename($projectPath);
    }

    $output->writeln([
      "",
      "✔️  Installation done! ☕\n",
      "🚀  Successfully created the <info>$displayPath</info> project",
      "👉  Get started with the following commands:\n",
      "<fg=gray>$ cd $displayPath</>",
      "<fg=gray>$ assegai serve</>\n\n\n",
    ]);

    $thankYouMessage = [
      "<comment>        Thanks for installing Assegai</comment> 🙏",
      "<fg=gray>Please consider donating to our open collective</>",
      "<fg=gray>    to help us maintain this package.</>\n\n",
    ];

    ['width' => $terminalWidth, 'height' => $terminalHeight] = TermInfo::windowSize();

    $thankYouMessageLines = [];

    foreach ($thankYouMessage as $line)
    {
      $lineLength = strlen($line);
      $offset = intval(($terminalWidth / 2) - ($lineLength / 2));
      $padding = str_repeat(' ', $offset);
      $thankYouMessageLines[] = $padding . $line;
    }

    $output->writeln($thankYouMessageLines);

    $donateLink = DONATION_LINK;
    $donationMessage = "🍷  Donate: <href=$donateLink>$donateLink</>\n";
    $lineLength = strlen($donationMessage) - strlen($donateLink);
    $offset = intval(($terminalWidth / 2) - ($lineLength / 2));
    $padding = str_repeat(' ', $offset);
    $output->writeln($padding . $donationMessage);
  }
}
