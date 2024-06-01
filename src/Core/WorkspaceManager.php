<?php

namespace Assegai\Console\Core;

use Assegai\Console\Util\Path;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class WorkspaceManager
{
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected FormatterHelper $formatter,
    protected QuestionHelper $questionHelper
  )
  {
  }

  public function init(?string $projectName = null, ?string $workingDirectory = null): void
  {
    $progress = new ProgressIndicator($this->output, 5);
    $workingDirectory = $directory ?? getcwd();
    $templatePath = Path::getTemplatesDirectory();
    $defaultProjectName = DEFAULT_PROJECT_NAME;

    if (! $projectName )
    {
      $projectNameQuestion = new Question("Project name?: ($defaultProjectName)", $defaultProjectName);
      $projectName = $this->questionHelper->ask($this->input, $this->output, $projectNameQuestion);
    }

    $progress->start("Creating project: $projectName\n");

    $i = 0;
    while ($i < 5)
    {
      $progress->advance();
      sleep(1);
      $i++;
    }
    $this->output->writeln("\nProject created: $projectName\n", OutputInterface::VERBOSITY_VERBOSE);

    $progress->finish();
  }
}