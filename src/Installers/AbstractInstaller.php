<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Interfaces\InstallerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractInstaller. Abstract class for installers.
 */
abstract class AbstractInstaller implements InstallerInterface
{

  /**
   * AbstractInstaller constructor.
   *
   * @param InputInterface $input The input interface.
   * @param OutputInterface $output The output interface.
   * @param FormatterHelper $formatter The formatter helper.
   * @param QuestionHelper $questionHelper The question helper.
   */
  public function __construct(
    protected InputInterface  $input,
    protected OutputInterface $output,
    protected FormatterHelper $formatter,
    protected QuestionHelper $questionHelper,
    protected string $projectPath,
  )
  {
  }

  /**
   * @inheritDoc
   */
  public abstract function install(): int;

  /**
   * @inheritDoc
   */
  public function uninstall(): int
  {
    // Do nothing.
    return Command::SUCCESS;
  }
}