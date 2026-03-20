<?php

namespace Assegai\Console\Commands\Schematic;

use Assegai\Console\Core\Schematics\Registry\SchematicWorkspaceConfig;
use Assegai\Console\Util\Config\ComposerConfig;
use Assegai\Console\Util\Inspector;
use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'schematic:init',
  description: 'Scaffold a local custom schematic in the workspace.'
)]
class SchematicInit extends Command
{
  public function configure(): void
  {
    $this
      ->addArgument('name', InputArgument::REQUIRED, 'The custom schematic name.')
      ->addOption(
        'directory',
        'd',
        InputOption::VALUE_REQUIRED,
        'The workspace directory where the schematic should be created.',
        getcwd() ?: '.',
      )
      ->addOption('php', null, InputOption::VALUE_NONE, 'Scaffold a class-backed schematic instead of a declarative one.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int
  {
    $workspace = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: '.'));
    $inspector = new Inspector($input, $output);

    if (! $inspector->isValidWorkspace($workspace)) {
      $output->writeln('<error>This is not a valid Assegai workspace.</error>');
      return Command::FAILURE;
    }

    $requestedName = trim((string) $input->getArgument('name'));

    if ($requestedName === '') {
      $output->writeln('<error>Invalid schematic name.</error>');
      return Command::FAILURE;
    }

    if (str_contains($requestedName, '/') || str_contains($requestedName, '\\')) {
      $output->writeln('<error>Schematic names should be a single kebab-case value, for example: loyalty-program.</error>');
      return Command::FAILURE;
    }

    $localPaths = SchematicWorkspaceConfig::localPaths($workspace);
    $schematicsRoot = Path::join($workspace, $localPaths[0] ?? 'schematics');
    $targetDirectory = Path::join($schematicsRoot, $requestedName);

    if (is_dir($targetDirectory)) {
      $output->writeln(sprintf('<error>Schematic already exists: %s</error>', $targetDirectory));
      return Command::FAILURE;
    }

    $templatesDirectory = Path::join($targetDirectory, 'templates');

    if (! mkdir($templatesDirectory, 0755, true) && ! is_dir($templatesDirectory)) {
      $output->writeln(sprintf('<error>Failed to create directory: %s</error>', $templatesDirectory));
      return Command::FAILURE;
    }

    $isPhpBacked = (bool) $input->getOption('php');
    $manifest = $isPhpBacked
      ? $this->buildPhpManifest($workspace, $requestedName, $input, $output)
      : $this->buildDeclarativeManifest($requestedName);

    $manifestPath = Path::join($targetDirectory, 'schematic.json');
    $templatePath = Path::join($templatesDirectory, 'service.php.stub');

    if (file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) === false) {
      $output->writeln(sprintf('<error>Failed to write manifest: %s</error>', $manifestPath));
      return Command::FAILURE;
    }

    if (file_put_contents($templatePath, $this->buildServiceTemplate()) === false) {
      $output->writeln(sprintf('<error>Failed to write template: %s</error>', $templatePath));
      return Command::FAILURE;
    }

    $output->writeln(sprintf('<info>CREATE</info> %s', ltrim(str_replace($workspace, '', $manifestPath), '/')));
    $output->writeln(sprintf('<info>CREATE</info> %s', ltrim(str_replace($workspace, '', $templatePath), '/')));

    if ($isPhpBacked) {
      $handlerPath = Path::join($targetDirectory, $this->resolveHandlerFilename($requestedName));

      if (file_put_contents($handlerPath, $this->buildPhpHandlerTemplate($workspace, $requestedName, $input, $output)) === false) {
        $output->writeln(sprintf('<error>Failed to write handler: %s</error>', $handlerPath));
        return Command::FAILURE;
      }

      $output->writeln(sprintf('<info>CREATE</info> %s', ltrim(str_replace($workspace, '', $handlerPath), '/')));
    }

    return Command::SUCCESS;
  }

  /**
   * @return array<string, mixed>
   */
  private function buildDeclarativeManifest(string $requestedName): array
  {
    return [
      'name' => $requestedName,
      'aliases' => [],
      'description' => sprintf('Generate %s scaffolding.', $requestedName),
      'requiresWorkspace' => true,
      'kind' => 'declarative',
      'arguments' => [
        [
          'name' => 'name',
          'description' => 'The feature name to generate.',
          'required' => true,
        ],
      ],
      'options' => [],
      'templates' => [
        [
          'source' => 'templates/service.php.stub',
          'target' => '__SOURCE_ROOT__/__NAME__/__NAME__Service.php',
        ],
      ],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function buildPhpManifest(
    string $workspace,
    string $requestedName,
    InputInterface $input,
    OutputInterface $output,
  ): array
  {
    return [
      'name' => $requestedName,
      'aliases' => [],
      'description' => sprintf('Generate %s scaffolding with custom PHP logic.', $requestedName),
      'requiresWorkspace' => true,
      'kind' => 'class',
      'arguments' => [
        [
          'name' => 'name',
          'description' => 'The feature name to generate.',
          'required' => true,
        ],
      ],
      'options' => [
        [
          'name' => 'domain',
          'shortcut' => 'D',
          'description' => 'An example custom option for company-specific schematics.',
          'acceptValue' => true,
          'valueRequired' => true,
          'default' => 'core',
        ],
      ],
      'handler' => [
        'class' => $this->resolveHandlerClass($workspace, $requestedName, $input, $output),
        'file' => $this->resolveHandlerFilename($requestedName),
      ],
    ];
  }

  private function buildServiceTemplate(): string
  {
    return <<<'PHP'
<?php

namespace __CURRENT_NAMESPACE__;

class __NAME__Service
{
}
PHP;
  }

  private function buildPhpHandlerTemplate(
    string $workspace,
    string $requestedName,
    InputInterface $input,
    OutputInterface $output,
  ): string
  {
    $handlerClass = $this->resolveHandlerClass($workspace, $requestedName, $input, $output);
    $namespace = substr($handlerClass, 0, (int) strrpos($handlerClass, '\\'));
    $className = basename(str_replace('\\', '/', $handlerClass));

    return <<<PHP
<?php

namespace $namespace;

use Assegai\Console\Core\Schematics\Custom\AbstractCustomSchematic;

class $className extends AbstractCustomSchematic
{
  public function build(): int
  {
    \$domain = (string) \$this->context()->getOption('domain', 'core');
    \$template = \$this->loadTemplate('templates/service.php.stub');
    \$content = \$this->replaceTokens(\$template . PHP_EOL . "// Domain: __OPTION_DOMAIN__" . PHP_EOL);

    return \$this->writeRelativeFile('__SOURCE_ROOT__/__NAME__/__NAME__Service.php', \$content);
  }
}
PHP;
  }

  private function resolveHandlerFilename(string $requestedName): string
  {
    return (new Text($requestedName))->pascalCase() . 'Schematic.php';
  }

  private function resolveHandlerClass(
    string $workspace,
    string $requestedName,
    InputInterface $input,
    OutputInterface $output,
  ): string
  {
    $baseNamespace = DEFAULT_NAMESPACE;
    $composerConfig = new ComposerConfig($input, $output, $workspace);

    if ($composerConfig->load() === Command::SUCCESS) {
      $namespaces = $composerConfig->get('autoload.psr-4', []);

      if (is_array($namespaces)) {
        foreach ($namespaces as $namespace => $path) {
          if ($path === 'src/' || $path === 'src') {
            $baseNamespace = rtrim((string) $namespace, '\\');
            break;
          }
        }
      }
    }

    return $baseNamespace . '\\Schematics\\' . (new Text($requestedName))->pascalCase() . 'Schematic';
  }
}
