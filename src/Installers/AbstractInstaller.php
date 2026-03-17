<?php

namespace Assegai\Console\Installers;

use Assegai\Console\Interfaces\InstallerInterface;
use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
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
  protected ?string $configuredDatabaseName = null;
  protected CliPrompt $prompts;

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
    $this->prompts = new CliPrompt($this->input, $this->output);
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

  public function getConfiguredDatabaseName(): ?string
  {
    return $this->configuredDatabaseName;
  }

  protected function getSuggestedDatabaseName(): string
  {
    $projectName = basename($this->projectPath);
    $projectConfigPath = Path::join($this->projectPath, 'assegai.json');

    if (file_exists($projectConfigPath)) {
      $projectConfig = new ProjectConfig($this->input, $this->output, $this->projectPath);

      if (Command::SUCCESS === $projectConfig->load()) {
        $projectName = $projectConfig->get('name', $projectName);
      }
    }

    $databaseName = trim((new Text((string) $projectName))->snakeCase(), '_');

    return $databaseName ?: 'app';
  }

  protected function getSuggestedSQLitePath(?string $databaseName = null): string
  {
    $databaseName ??= $this->getSuggestedDatabaseName();

    return Path::join('.data', "$databaseName.sq3");
  }
}
