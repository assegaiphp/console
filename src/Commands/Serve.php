<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'serve',
  description: 'Serve the project',
  aliases: ['s'])
]
class Serve extends Command
{
  public function configure(): void {
    $this->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the project on', null);
    $this->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'The host to serve the project on', null);
    $this->addOption('https', 's', InputOption::VALUE_NONE, 'Serve the project over HTTPS');
    $this->addOption('root', 'r', InputOption::VALUE_OPTIONAL, 'The root directory to serve the project from', getcwd());
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    /** @var FormatterHelper $formatter */
    $formatter = $this->getHelper('formatter');

    $projectConfig = new ProjectConfig($input, $output);
    $projectConfig->load();

    $port = $input->getOption('port') ?? $projectConfig->get('development')->get('server')['port'] ?? DEFAULT_DEV_SERVER_PORT;
    $host = $input->getOption('host') ?? $projectConfig->get('development')->get('server')['host'] ?? DEFAULT_DEV_SERVER_HOST;
    $https = $input->getOption('https') ?? false;
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

    $output->writeln($formatter->formatBlock("Serving the project on $uri", 'info', true));

    return Command::SUCCESS;
  }
}