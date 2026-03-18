<?php

namespace Assegai\Console\Core\Modules;

use Assegai\Console\Prompts\CliPrompt;
use Assegai\Console\Util\Path;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Applies module-level data_source configuration across a workspace.
 */
class ModuleDataSourceConfigurator
{
  protected CliPrompt $prompts;

  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output,
    protected ?QuestionHelper $questionHelper,
    protected string $projectPath,
  )
  {
    $this->prompts = new CliPrompt($this->input, $this->output);
  }

  /**
   * Prompt for the target modules, then apply the selected data source.
   */
  public function promptAndConfigure(string $dataSourceName): int
  {
    if (! $this->input->isInteractive()) {
      $this->output->writeln("<comment>Skipping module data_source enablement for <info>$dataSourceName</info> in non-interactive mode.</comment>");
      return Command::SUCCESS;
    }

    $modules = $this->discoverModules();

    if (empty($modules)) {
      $this->output->writeln('<comment>No modules found to update.</comment>');
      return Command::SUCCESS;
    }

    $selection = $this->askTargetSelection($dataSourceName);

    $targetModulePaths = match ($selection) {
      'all' => array_map(
        static fn(array $module): string => $module['relativePath'],
        $modules
      ),
      'specific' => $this->askSpecificModules($modules, $dataSourceName),
      default => [],
    };

    if (empty($targetModulePaths)) {
      $this->output->writeln('<comment>Leaving module data_source configuration unchanged.</comment>');
      return Command::SUCCESS;
    }

    $selectedModules = $this->resolveModules($targetModulePaths);
    $collisions = $this->findCollisions($selectedModules, $dataSourceName);
    $overwriteCollisions = false;

    if ($collisions) {
      $this->writeCollisionReport($collisions, $dataSourceName);
      $overwriteCollisions = $this->prompts->confirm(
        'Overwrite the conflicting module data_source values?',
        false
      );
    }

    return $this->applyConfiguration($selectedModules, $dataSourceName, $overwriteCollisions, false);
  }

  /**
   * Apply the data source to every discovered module.
   */
  public function configureForAllModules(string $dataSourceName, bool $overwriteCollisions = false): int
  {
    return $this->applyConfiguration(
      $this->discoverModules(),
      $dataSourceName,
      $overwriteCollisions,
      true
    );
  }

  /**
   * Apply the data source to the provided module paths.
   *
   * @param string[] $moduleRelativePaths Module paths relative to src/
   */
  public function configureForModules(string $dataSourceName, array $moduleRelativePaths, bool $overwriteCollisions = false): int
  {
    return $this->applyConfiguration(
      $this->resolveModules($moduleRelativePaths),
      $dataSourceName,
      $overwriteCollisions,
      true
    );
  }

  /**
   * @return array<int, array{relativePath: string, displayPath: string, label: string}>
   */
  protected function discoverModules(): array
  {
    $srcPath = Path::join($this->projectPath, 'src');

    if (! is_dir($srcPath)) {
      return [];
    }

    $modules = [];
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($srcPath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
      if (! $item->isFile() || ! str_ends_with($item->getFilename(), 'Module.php')) {
        continue;
      }

      $absolutePath = Path::normalize($item->getPathname());
      $relativePath = ltrim(str_replace(Path::normalize($srcPath), '', $absolutePath), '/');
      $relativePath = str_replace('\\', '/', $relativePath);

      $modules[] = [
        'relativePath' => $relativePath,
        'displayPath' => 'src/' . $relativePath,
        'label' => preg_replace('/\.php$/', '', $relativePath) ?: $relativePath,
      ];
    }

    usort(
      $modules,
      static fn(array $left, array $right): int => strcmp($left['relativePath'], $right['relativePath'])
    );

    return $modules;
  }

  /**
   * @param array<int, array{relativePath: string, displayPath: string, label: string}> $modules
   */
  protected function askSpecificModules(array $modules, string $dataSourceName): array
  {
    $choices = [];

    foreach ($modules as $module) {
      $choices[$module['relativePath']] = $module['label'];
    }

    return array_values(array_map(
      'strval',
      $this->prompts->multiselect(
        "Which modules should use $dataSourceName?",
        $choices
      )
    ));
  }

  protected function askTargetSelection(string $dataSourceName): string
  {
    return (string) $this->prompts->select(
      "Enable $dataSourceName for which modules?",
      [
        'all' => 'All modules',
        'specific' => 'Specific modules',
        'none' => 'None',
      ],
      'all',
      "This can be configured later"
    );
  }

  /**
   * @param string[] $moduleRelativePaths
   * @return array<int, array{relativePath: string, displayPath: string, label: string}>
   */
  protected function resolveModules(array $moduleRelativePaths): array
  {
    $discoveredModules = [];

    foreach ($this->discoverModules() as $module) {
      $discoveredModules[$module['relativePath']] = $module;
    }

    $resolvedModules = [];

    foreach (array_values(array_unique($moduleRelativePaths)) as $relativePath) {
      if (! isset($discoveredModules[$relativePath])) {
        continue;
      }

      $resolvedModules[] = $discoveredModules[$relativePath];
    }

    return $resolvedModules;
  }

  /**
   * @param array<int, array{relativePath: string, displayPath: string, label: string}> $modules
   * @return array<int, array{relativePath: string, displayPath: string, label: string, existingDataSource: string}>
   */
  protected function findCollisions(array $modules, string $dataSourceName): array
  {
    $collisions = [];

    foreach ($modules as $module) {
      $existingDataSource = $this->getExistingDataSource($module['relativePath']);

      if ($existingDataSource === null || $existingDataSource === $dataSourceName) {
        continue;
      }

      $collisions[] = [
        ...$module,
        'existingDataSource' => $existingDataSource,
      ];
    }

    return $collisions;
  }

  /**
   * @param array<int, array{relativePath: string, displayPath: string, label: string, existingDataSource: string}> $collisions
   */
  protected function writeCollisionReport(array $collisions, string $dataSourceName): void
  {
    $this->output->writeln('');
    $this->output->writeln("<comment>The following modules already use a different data_source and would collide with <info>$dataSourceName</info>:</comment>");

    foreach ($collisions as $collision) {
      $this->output->writeln(sprintf(
        ' - <info>%s</info>: <comment>%s</comment>',
        $collision['displayPath'],
        $collision['existingDataSource']
      ));
    }

    $this->output->writeln('');
  }

  /**
   * @param array<int, array{relativePath: string, displayPath: string, label: string}> $selectedModules
   */
  protected function applyConfiguration(
    array $selectedModules,
    string $dataSourceName,
    bool $overwriteCollisions,
    bool $reportCollisions,
  ): int
  {
    if (empty($selectedModules)) {
      $this->output->writeln('<comment>No modules selected.</comment>');
      return Command::SUCCESS;
    }

    $collisions = $this->findCollisions($selectedModules, $dataSourceName);

    if ($reportCollisions && $collisions) {
      $this->writeCollisionReport($collisions, $dataSourceName);
    }

    $updatedModules = 0;
    $unchangedModules = 0;
    $skippedModules = 0;

    foreach ($selectedModules as $module) {
      $existingDataSource = $this->getExistingDataSource($module['relativePath']);

      if ($existingDataSource === $dataSourceName) {
        $unchangedModules++;
        continue;
      }

      if ($existingDataSource !== null && ! $overwriteCollisions) {
        $skippedModules++;
        continue;
      }

      $status = $existingDataSource === null
        ? $this->insertDataSource($module['relativePath'], $dataSourceName)
        : $this->replaceDataSource($module['relativePath'], $dataSourceName);

      if ($status !== Command::SUCCESS) {
        return $status;
      }

      $updatedModules++;
    }

    if ($updatedModules > 0) {
      $this->output->writeln([
          "",
          "<info>Applied data_source <comment>$dataSourceName</comment> to $updatedModules module(s).</info>"
      ]);
    }

    if ($skippedModules > 0) {
      $this->output->writeln("<comment>Left $skippedModules module(s) unchanged because they already define a different data_source.</comment>");
    }

    if ($updatedModules === 0 && $unchangedModules > 0 && $skippedModules === 0) {
      $this->output->writeln('<comment>The selected modules already use that data_source.</comment>');
    }

    return Command::SUCCESS;
  }

  protected function getExistingDataSource(string $moduleRelativePath): ?string
  {
    $contents = file_get_contents($this->getModuleFilename($moduleRelativePath));

    if ($contents === false) {
      throw new RuntimeException("Failed to read module file: $moduleRelativePath");
    }

    $context = $this->locateConfigBody($contents);

    if ($context === null) {
      return null;
    }

    if (! preg_match(
      '/(?P<keyQuote>[\'"])data_source(?P=keyQuote)\s*=>\s*(?P<valueQuote>[\'"])(?P<value>(?:\\\\.|(?!\k<valueQuote>).)*)\k<valueQuote>/s',
      $context['configBody'],
      $matches
    )) {
      return null;
    }

    return stripcslashes($matches['value']);
  }

  protected function insertDataSource(string $moduleRelativePath, string $dataSourceName): int
  {
    $previousWorkingDirectory = getcwd();

    if ($previousWorkingDirectory === false) {
      throw new RuntimeException('Failed to determine the current working directory.');
    }

    chdir($this->projectPath);

    try {
      return update_module_file(
        ['config' => ["'data_source' => " . $this->quotePhpString($dataSourceName)]],
        $moduleRelativePath,
        $this->output
      );
    } finally {
      chdir($previousWorkingDirectory);
    }
  }

  protected function replaceDataSource(string $moduleRelativePath, string $dataSourceName): int
  {
    $filename = $this->getModuleFilename($moduleRelativePath);
    $contents = file_get_contents($filename);

    if ($contents === false) {
      throw new RuntimeException("Failed to read module file: $filename");
    }

    $context = $this->locateConfigBody($contents);

    if ($context === null) {
      return $this->insertDataSource($moduleRelativePath, $dataSourceName);
    }

    $updatedConfigBody = preg_replace_callback(
      '/(?P<prefix>(?P<keyQuote>[\'"])data_source(?P=keyQuote)\s*=>\s*)(?P<valueQuote>[\'"])(?P<value>(?:\\\\.|(?!\k<valueQuote>).)*)\k<valueQuote>/s',
      fn(array $matches): string => $matches['prefix']
        . $matches['valueQuote']
        . $this->escapePhpString($dataSourceName, $matches['valueQuote'])
        . $matches['valueQuote'],
      $context['configBody'],
      1,
      $replacements
    );

    if (! $replacements || $updatedConfigBody === null) {
      throw new RuntimeException("Failed to update the data_source in $moduleRelativePath.");
    }

    $updatedModuleBody = substr_replace(
      $context['moduleBody'],
      $updatedConfigBody,
      $context['configBodyOffset'],
      strlen($context['configBody'])
    );

    $updatedContents = substr_replace(
      $contents,
      $updatedModuleBody,
      $context['moduleBodyOffset'],
      strlen($context['moduleBody'])
    );

    if (false === file_put_contents($filename, $updatedContents)) {
      throw new RuntimeException("Failed to write module file: $filename");
    }

    $this->output->writeln('<fg=blue>UPDATE</> ' . $moduleRelativePath);

    return Command::SUCCESS;
  }

  protected function getModuleFilename(string $moduleRelativePath): string
  {
    return Path::join($this->projectPath, 'src', $moduleRelativePath);
  }

  /**
   * @return array{moduleBody: string, moduleBodyOffset: int, configBody: string, configBodyOffset: int}|null
   */
  protected function locateConfigBody(string $contents): ?array
  {
    $moduleMatches = [];

    if (
      false === preg_match(
        '/#\[Module\((?<body>[\s\S]*?)\)\]/',
        $contents,
        $moduleMatches,
        PREG_OFFSET_CAPTURE
      )
      || ! isset($moduleMatches['body'][0], $moduleMatches['body'][1])
    ) {
      return null;
    }

    $moduleBody = $moduleMatches['body'][0];
    $configMatches = [];

    if (
      false === preg_match(
        '/(?P<indent>^[ \t]*)config(?P<afterName>\s*:\s*)\[(?P<body>.*?)\](?P<comma>,?)/ms',
        $moduleBody,
        $configMatches,
        PREG_OFFSET_CAPTURE
      )
      || ! isset($configMatches['body'][0], $configMatches['body'][1])
    ) {
      return null;
    }

    return [
      'moduleBody' => $moduleBody,
      'moduleBodyOffset' => $moduleMatches['body'][1],
      'configBody' => $configMatches['body'][0],
      'configBodyOffset' => $configMatches['body'][1],
    ];
  }

  protected function quotePhpString(string $value): string
  {
    return "'" . $this->escapePhpString($value, "'") . "'";
  }

  protected function escapePhpString(string $value, string $quote): string
  {
    return str_replace(
      ['\\', $quote],
      ['\\\\', '\\' . $quote],
      $value
    );
  }
}
