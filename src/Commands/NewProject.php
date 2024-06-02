<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Core\WorkspaceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'new',
  description: 'Creates a new project',
  aliases: ['n']
)]
class NewProject extends Command
{
  public function configure(): void
  {
    $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the project');
    $this->addOption('directory', 'd', InputArgument::OPTIONAL, 'The path to create the project in', getcwd());
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $projectName = $input->getArgument('name');
    $projectDirectory = $input->getOption('directory');

    /** @var FormatterHelper $formatter */
    $formatter = $this->getHelper('formatter');
    /** @var QuestionHelper $questionHelper */
    $questionHelper = $this->getHelper('question');
    $workspaceManager = new WorkspaceManager($input, $output, $formatter, $questionHelper);

    $workspaceManager->init($projectName, $projectDirectory);

    return Command::SUCCESS;
  }
}