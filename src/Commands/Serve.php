<?php

namespace Assegai\Console\Commands;

use Assegai\Console\Api\WorkspaceApiBridge;
use Assegai\Console\Util\Config\ProjectConfig;
use Assegai\Console\Util\Path;
use Assegai\Console\WebComponents\HotReload\WebComponentHotReloadState;
use Assegai\Core\Runtimes\OpenSwoole\OpenSwooleRuntimeInspector;
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
  private const array SUPPORTED_RUNTIMES = ['php', 'openswoole', 'swoole'];

  protected ?ProjectConfig $projectConfig = null;
  protected ?bool $open = null;

  public function configure(): void
  {
    $this
      ->addOption('dev', null, InputOption::VALUE_NONE, 'Serve in development mode and run the Web Components watcher alongside the server.')
      ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the project on', null)
      ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'The host to serve the project on', null)
      ->addOption('runtime', null, InputOption::VALUE_OPTIONAL, 'The HTTP runtime to serve with (php, openswoole)', null)
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
    $runtime = $this->resolveServeRuntime($input);
    $https = $input->getOption('https') ?? false;
    $this->open = $input->getOption('open') ?: $this->projectConfig->get('development.server.openBrowser') ?? false;
    $router = Path::join($root, 'index.php');
    $bootstrap = Path::join($root, 'bootstrap.php');
    $scheme = $https ? 'https' : 'http';
    $uri = "$host:$port";
    $certPath = Path::join(Path::getCertificatesDirectory(), 'localhost.crt');
    $keyPath = Path::join(Path::getCertificatesDirectory(), 'localhost.key');

    if ($runtime === null) {
      $output->writeln('<error>Unsupported runtime. Supported runtimes: php, openswoole.</error>');
      return Command::FAILURE;
    }

    $runtimeValidationError = $this->validateRuntimeAvailability($runtime);

    if ($runtimeValidationError !== null) {
      $output->writeln('<error>' . $runtimeValidationError . '</error>');
      return Command::FAILURE;
    }

    $runtimeConfigurationError = $this->validateRuntimeConfiguration($runtime, (string) $host, (int) $port);

    if ($runtimeConfigurationError !== null) {
      $output->writeln('<error>' . $runtimeConfigurationError . '</error>');
      return Command::FAILURE;
    }

    if ($runtime !== 'php' && $https) {
      $output->writeln('<error>The selected runtime does not support the --https serve path yet.</error>');
      return Command::FAILURE;
    }

    $output->writeln($formatter->formatBlock([
      $dev
        ? "Assegai dev server listening on $scheme://$uri using the $runtime runtime"
        : "Assegai app listening on $scheme://$uri using the $runtime runtime",
      "CTRL+C to stop the server"
    ], 'question', true));
    $output->writeln('');
    $resultCode = 0;
    $command = $this->buildServeCommand(
      runtime: $runtime,
      uri: $uri,
      workingDirectory: $root,
      host: (string) $host,
      port: (int) $port,
      router: $router,
      bootstrap: $bootstrap,
      https: (bool) $https,
      certPath: $certPath,
      keyPath: $keyPath,
    );

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

  protected function resolveServeRuntime(InputInterface $input): ?string
  {
    $runtime = strtolower(trim((string) (
      $input->getOption('runtime')
      ?? $this->projectConfig?->get('development.server.runtime')
      ?? 'php'
    )));

    if ($runtime === '') {
      $runtime = 'php';
    }

    if (!in_array($runtime, self::SUPPORTED_RUNTIMES, true)) {
      return null;
    }

    return $runtime === 'swoole' ? 'openswoole' : $runtime;
  }

  protected function validateRuntimeAvailability(string $runtime): ?string
  {
    if ($runtime !== 'openswoole') {
      return null;
    }

    return (new OpenSwooleRuntimeInspector())->getAvailabilityError();
  }

  protected function validateRuntimeConfiguration(string $runtime, string $host, int $port): ?string
  {
    if (trim($host) === '') {
      return 'The serve host must be a non-empty string.';
    }

    if ($port < 1 || $port > 65535) {
      return 'The serve port must be between 1 and 65535.';
    }

    if ($runtime !== 'openswoole') {
      return null;
    }

    $settings = $this->projectConfig?->get('development.server.openswoole');

    if (!is_array($settings)) {
      return null;
    }

    return $this->validateOpenSwooleSettings($settings);
  }

  /**
   * @param array<string, mixed> $settings
   */
  protected function validateOpenSwooleSettings(array $settings): ?string
  {
    $supportedSettings = [
      'workerNum',
      'taskWorkerNum',
      'maxRequest',
      'enableCoroutine',
      'hookFlags',
      'daemonize',
      'logFile',
      'pidFile',
    ];

    foreach (array_keys($settings) as $key) {
      if (is_string($key) && in_array($key, $supportedSettings, true)) {
        continue;
      }

      return sprintf(
        'Unsupported OpenSwoole setting [%s]. Supported settings are: %s.',
        (string) $key,
        implode(', ', $supportedSettings),
      );
    }

    $integerRules = [
      'workerNum' => 1,
      'taskWorkerNum' => 0,
      'maxRequest' => 0,
    ];

    foreach ($integerRules as $key => $minimum) {
      if (!array_key_exists($key, $settings)) {
        continue;
      }

      $value = is_string($settings[$key]) ? trim($settings[$key]) : $settings[$key];

      if ($value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
        return sprintf('The OpenSwoole setting [%s] must be an integer.', $key);
      }

      if ((int) $value < $minimum) {
        return $minimum === 1
          ? sprintf('The OpenSwoole setting [%s] must be greater than or equal to 1.', $key)
          : sprintf('The OpenSwoole setting [%s] must be greater than or equal to %d.', $key, $minimum);
      }
    }

    foreach (['enableCoroutine', 'daemonize'] as $key) {
      if (!array_key_exists($key, $settings)) {
        continue;
      }

      if (filter_var($settings[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
        return sprintf('The OpenSwoole setting [%s] must be a boolean.', $key);
      }
    }

    foreach (['logFile', 'pidFile'] as $key) {
      if (!array_key_exists($key, $settings)) {
        continue;
      }

      if (!is_scalar($settings[$key]) || trim((string) $settings[$key]) === '') {
        return sprintf('The OpenSwoole setting [%s] must be a non-empty string.', $key);
      }
    }

    if (array_key_exists('hookFlags', $settings) && !$this->isValidOpenSwooleHookFlags($settings['hookFlags'])) {
      return 'The OpenSwoole setting [hookFlags] must be an integer, string, list, or null.';
    }

    return null;
  }

  protected function isValidOpenSwooleHookFlags(mixed $hookFlags): bool
  {
    if ($hookFlags === null || is_int($hookFlags) || is_bool($hookFlags)) {
      return true;
    }

    if (is_string($hookFlags)) {
      return trim($hookFlags) !== '';
    }

    if (!is_array($hookFlags)) {
      return false;
    }

    foreach ($hookFlags as $flag) {
      if (!is_scalar($flag) || trim((string) $flag) === '') {
        return false;
      }
    }

    return true;
  }

  protected function buildServeCommand(
    string $runtime,
    string $uri,
    string $workingDirectory,
    string $host,
    int $port,
    string $router,
    string $bootstrap,
    bool $https = false,
    ?string $certPath = null,
    ?string $keyPath = null,
  ): string
  {
    $environmentPrefix = $this->buildRuntimeEnvironmentPrefix($runtime, $host, $port, $workingDirectory);

    if ($runtime === 'php') {
      $command = $environmentPrefix . " php -S $uri " . escapeshellarg($router);

      if ($https && $certPath !== null && $keyPath !== null) {
        $command .= ' --cert ' . escapeshellarg($certPath) . ' --key ' . escapeshellarg($keyPath);
      }

      return $command;
    }

    return $environmentPrefix . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bootstrap);
  }

  protected function buildRuntimeEnvironmentPrefix(string $runtime, string $host, int $port, string $workingDirectory): string
  {
    return sprintf(
      'ASSEGAI_RUNTIME=%s ASSEGAI_HOST=%s ASSEGAI_PORT=%s ASSEGAI_WORKING_DIR=%s',
      escapeshellarg($runtime),
      escapeshellarg($host),
      escapeshellarg((string) $port),
      escapeshellarg($workingDirectory),
    );
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
