<?php

namespace Assegai\Console\Core\Schematics\Custom;

use Assegai\Console\Core\Interfaces\SchematicInterface;
use Assegai\Console\Core\Schematics\Registry\SchematicContext;
use Assegai\Console\Core\Schematics\Registry\SchematicTemplateVariables;
use Assegai\Console\Util\Path;
use Symfony\Component\Console\Command\Command;

abstract class AbstractCustomSchematic implements SchematicInterface
{
  public function __construct(protected SchematicContext $context)
  {
    $this->configure();
  }

  public function configure(): void
  {
  }

  public function prepareBuild(): int
  {
    return Command::SUCCESS;
  }

  public function finalizeBuild(): int
  {
    return Command::SUCCESS;
  }

  public function prepareTearDown(): int
  {
    return Command::SUCCESS;
  }

  public function tearDown(): int
  {
    return Command::SUCCESS;
  }

  public function finalizeTearDown(): int
  {
    return Command::SUCCESS;
  }

  protected function context(): SchematicContext
  {
    return $this->context;
  }

  /**
   * @param array<string, string>|null $variables
   */
  protected function replaceTokens(string $content, ?array $variables = null): string
  {
    return SchematicTemplateVariables::replace($content, $variables ?? $this->context->getTemplateVariables());
  }

  protected function loadTemplate(string $relativePath): string
  {
    $basePath = $this->context->definition->metadata['basePath'] ?? null;

    if (! is_string($basePath) || $basePath === '') {
      throw new \RuntimeException('This schematic does not have a base path for templates.');
    }

    $templatePath = Path::join($basePath, $relativePath);

    if (! is_file($templatePath)) {
      throw new \RuntimeException(sprintf('Template not found: %s', $templatePath));
    }

    return file_get_contents($templatePath) ?: '';
  }

  protected function writeRelativeFile(string $relativePath, string $content): int
  {
    $target = Path::join($this->context->getWorkspace(), $this->replaceTokens($relativePath));
    $directory = dirname($target);

    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
      $this->context->output->writeln(sprintf('<error>Failed to create directory: %s</error>', $directory));
      return Command::FAILURE;
    }

    if (file_exists($target)) {
      $this->context->output->writeln(sprintf('<error>File already exists: %s</error>', $target));
      return Command::FAILURE;
    }

    $bytes = file_put_contents($target, $this->replaceTokens($content));

    if ($bytes === false) {
      $this->context->output->writeln(sprintf('<error>Failed to write file: %s</error>', $target));
      return Command::FAILURE;
    }

    $relativeDisplayPath = ltrim(str_replace($this->context->getWorkspace(), '', $target), '/');
    $this->context->output->writeln(sprintf('<info>CREATE</info> %s (%d bytes)', $relativeDisplayPath, $bytes));

    return Command::SUCCESS;
  }
}
