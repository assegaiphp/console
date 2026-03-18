<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Api\WorkspaceApiBridge;
use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Path;
use Assegai\Console\WebComponents\HotReload\WebComponentHotReloadState;
use LogicException;
use RuntimeException;
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
      ->addOption('dev', null, InputOption::VALUE_NONE, 'Serve in development mode and run the Web Components watcher alongside the server.')
      ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the project on', null)
      ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'The host to serve the project on', null)
      ->addOption('https', 's', InputOption::VALUE_NONE, 'Serve the project over HTTPS')
      ->addOption('root', 'r', InputOption::VALUE_OPTIONAL, 'The root directory to serve the project from', getcwd())
      ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open the url in the default browser');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $formatter = $this->getFormatterHelper();
    $root = $input->getOption('root') ?? (getcwd() ?: '');

    $this->projectConfig = new ProjectConfig($input, $output, $root);

    if (Command::SUCCESS !== $this->projectConfig->load()) {
      $output->writeln("<error>Failed to load the project configuration</error>");
      return Command::FAILURE;
    }

    if (Command::SUCCESS !== $this->exportApiDocsIfConfigured($root, $output)) {
      return Command::FAILURE;
    }

    $dev = (bool)$input->getOption('dev');
    $port = $input->getOption('port') ?? $this->projectConfig->get('development.server.port') ?? DEFAULT_DEV_SERVER_PORT;
    $host = $input->getOption('host') ?? $this->projectConfig->get('development.server.host') ?? DEFAULT_DEV_SERVER_HOST;
    $https = $input->getOption('https') ?? false;
    $this->open = $input->getOption('open') ?: $this->projectConfig->get('development.server.openBrowser') ?? false;
    $router = Path::join($root, 'index.php');
    $scheme = $https ? 'https' : 'http';
    $uri = "$host:$port";
    $certPath = Path::join(Path::getCertificatesDirectory(), 'localhost.crt');
    $keyPath = Path::join(Path::getCertificatesDirectory(), 'localhost.key');

    $output->writeln($formatter->formatBlock([
      $dev
        ? "Assegai dev server listening on $scheme://$uri"
        : "Assegai app listening on $scheme://$uri",
      "CTRL+C to stop the server"
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

    $watchProcess = null;

    try {
      if ($dev) {
        $watchProcess = $this->startWebComponentWatchProcess($root, $output);

        if ($watchProcess === false) {
          $output->writeln("<error>Failed to start Web Components watch mode</error>");
          return Command::FAILURE;
        }
      }

      $resultCode = $this->runServeCommand($command);
    } finally {
      if ($dev) {
        $this->stopWebComponentWatchProcess($watchProcess, $root);
      }
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

    if (false === shell_exec("which sensible-browser")) {
      $output->writeln("<error>Failed to find the sensible-browser command</error>", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    return true;
  }

  /**
   * @return resource|false|null
   */
  protected function startWebComponentWatchProcess(string $root, OutputInterface $output): mixed
  {
    $entrypoint = $this->resolveConsoleEntrypoint();

    if ($entrypoint === null) {
      return false;
    }

    $command = sprintf(
      '%s %s wc:watch --directory %s',
      escapeshellarg(PHP_BINARY),
      escapeshellarg($entrypoint),
      escapeshellarg($root)
    );

    $descriptors = [
      0 => ['file', 'php://stdin', 'r'],
      1 => ['file', 'php://stdout', 'w'],
      2 => ['file', 'php://stderr', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $root);

    if (!is_resource($process)) {
      return false;
    }

    $output->writeln(
      $this->getFormatterHelper()->formatBlock('Starting Web Components watch mode', 'question', true),
      OutputInterface::VERBOSITY_VERBOSE
    );

    usleep(200000);
    $status = proc_get_status($process);

    if (!$status['running']) {
      $exitCode = is_int($status['exitcode']) ? $status['exitcode'] : Command::FAILURE;
      proc_close($process);

      return $exitCode === 0 ? null : false;
    }

    return $process;
  }

  protected function stopWebComponentWatchProcess(mixed $process, string $root): void
  {
    if (is_resource($process)) {
      proc_terminate($process);
      proc_close($process);
    }

    (new WebComponentHotReloadState($root))->deactivate();
  }

  protected function runServeCommand(string $command): int
  {
    $statusCode = 0;

    if (false === passthru($command, $statusCode)) {
      return Command::FAILURE;
    }

    return $statusCode;
  }

  protected function exportApiDocsIfConfigured(string $root, OutputInterface $output): int
  {
    if (!$this->shouldAutoExportOpenApi()) {
      return Command::SUCCESS;
    }

    return $this->writeOpenApiExport($root, $this->resolveApiDocsExportPath($root), $output);
  }

  protected function shouldAutoExportOpenApi(): bool
  {
    return (bool) ($this->projectConfig?->get('apiDocs.exportOnServe', false) ?? false);
  }

  protected function resolveApiDocsExportPath(string $root): string
  {
    $configuredPath = trim((string) ($this->projectConfig?->get('apiDocs.exportPath', 'generated/openapi.json') ?? 'generated/openapi.json'));

    if ($configuredPath === '') {
      $configuredPath = 'generated/openapi.json';
    }

    if (str_starts_with($configuredPath, '/')) {
      return Path::normalize($configuredPath);
    }

    return Path::join($root, $configuredPath);
  }

  protected function writeOpenApiExport(string $root, string $outputFile, OutputInterface $output): int
  {
    try {
      $bridge = new WorkspaceApiBridge($root);
      $document = $bridge->generateOpenApiDocument();
      $directory = dirname($outputFile);

      if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Failed to create the OpenAPI export directory.');
      }

      $payload = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

      if (false === file_put_contents($outputFile, $payload)) {
        throw new RuntimeException('Failed to write the OpenAPI export.');
      }

      $output->writeln('<info>GENERATED</info> ' . $outputFile, OutputInterface::VERBOSITY_VERBOSE);

      return Command::SUCCESS;
    } catch (RuntimeException $exception) {
      $output->writeln('<error>' . $exception->getMessage() . '</error>');
      return Command::FAILURE;
    }
  }

  protected function resolveConsoleEntrypoint(): ?string
  {
    $candidates = array_filter([
      isset($_SERVER['argv'][0]) && is_string($_SERVER['argv'][0]) ? realpath($_SERVER['argv'][0]) ?: null : null,
      realpath(__DIR__ . '/../../bin/assegai') ?: null,
    ]);

    foreach ($candidates as $candidate) {
      if (is_string($candidate) && is_file($candidate)) {
        return $candidate;
      }
    }

    return null;
  }

  protected function getFormatterHelper(): FormatterHelper
  {
    try {
      /** @var FormatterHelper $formatter */
      $formatter = $this->getHelper('formatter');
      return $formatter;
    } catch (LogicException) {
      return new FormatterHelper();
    }
  }
}
