<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'serve',
  description: 'Serve the project',
  aliases: ['s'])
]
class Serve extends Command
{
  protected ?ProjectConfig $projectConfig = null;
  protected ?bool $open = null;

  public function configure(): void
  {
    $this
      ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the project on', null)
      ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'The host to serve the project on', null)
      ->addOption('https', 's', InputOption::VALUE_NONE, 'Serve the project over HTTPS')
      ->addOption('root', 'r', InputOption::VALUE_OPTIONAL, 'The root directory to serve the project from', getcwd())
      ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open the url in the default browser');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    /** @var FormatterHelper $formatter */
    $formatter = $this->getHelper('formatter');

    $this->projectConfig = new ProjectConfig($input, $output);

    if (Command::SUCCESS !== $this->projectConfig->load()) {
      $output->writeln("<error>Failed to load the project configuration</error>");
      return Command::FAILURE;
    }

    $port = $input->getOption('port') ?? $this->projectConfig->get('development.server.port') ?? DEFAULT_DEV_SERVER_PORT;
    $host = $input->getOption('host') ?? $this->projectConfig->get('development.server.host') ?? DEFAULT_DEV_SERVER_HOST;
    $https = $input->getOption('https') ?? false;
    $this->open = $input->getOption('open') ?? $this->projectConfig->get('development.server.openBrowser') ?? false;
    $router = Path::join($input->getOption('root') ?? getcwd(), 'index.php');
    $scheme = $https ? 'https' : 'http';
    $uri = "$host:$port";
    $certPath = Path::join(Path::getCertificatesDirectory(), 'localhost.crt');
    $keyPath = Path::join(Path::getCertificatesDirectory(), 'localhost.key');

    $output->writeln($formatter->formatBlock([
      "Assegai app listening on $scheme://$uri",
      'CTRL+C to stop the server'
    ], 'question', true));
    $output->writeln('');
    $resultCode = 0;
    $command = "php -S $uri $router";
    if ($https) {
      $command .= " --cert $certPath --key $keyPath";
    }

    $output->writeln($formatter->formatBlock("Serving the project on $uri", 'question', true), OutputInterface::VERBOSITY_VERBOSE);
    if ($this->canOpenBrowser()) {
      $output->writeln([
        "",
        $formatter->formatBlock("Opening the browser...", 'question', true),
        "",
      ], OutputInterface::VERBOSITY_VERBOSE);

      if (false === passthru("sensible-browser $scheme://$uri", $resultCode)) {
        $output->writeln('');
        $output->writeln("<error>Failed to open the browser</error>");
        return Command::FAILURE;
      }
    }

    if (false === passthru($command, $resultCode)) {
      $output->writeln('');
      $output->writeln("<error>Failed to serve the project on $uri</error>");
      return Command::FAILURE;
    }

    if ($resultCode !== 0) {
      $output->writeln('');
      $output->writeln("<error>Failed to serve the project on $uri</error>");
      return Command::FAILURE;
    }

    return Command::SUCCESS;
  }

  /**
   * Returns true if the browser can be opened, false otherwise.
   *
   * @return bool Returns true if the browser can be opened, false otherwise.
   */
  public function canOpenBrowser(): bool
  {
    $output = new ConsoleOutput();

    if ($this->open === false) {
      return false;
    }

    if (false === `which sensible-browser`) {
      $output->writeln("<error>Failed to find the sensible-browser command</error>", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    return true;
  }
}