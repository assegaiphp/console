<?php

namespace Assegai\Console\Core\Schematics;

use Assegai\Console\Util\Path;
use Assegai\Console\Util\Text;
use Override;
use Symfony\Component\Console\Command\Command;

/**
 * Class ComponentSchematic
 *
 * @package Assegai\Console\Core\Schematics
 */
class ComponentSchematic extends AbstractClassSchematic
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $name = new Text($this->name);
    $selector = 'app-' . $name->kebabCase();
    $templateUrl = './' . $name->pascalCase() . 'Component.twig';
    $styleUrl = './' . $name->pascalCase() . 'Component.css';
    $this->suffix = 'component';
    $this->namespaceSuffix = $this->getResolvedNamespaceSuffix();
    $this->imports = [
      'Assegai\Core\Attributes\Component',
      'Assegai\Core\Components\AssegaiComponent'
    ];
    $this->attributes = [<<<COMPONENT
Component(
  selector: '$selector',
  templateUrl: '$templateUrl',
  styleUrls: ['$styleUrl'])
COMPONENT
];
    $this->parent = 'AssegaiComponent';
  }

  /**
   * @inheritDoc
   */
  #[Override]
  public function getModuleUpdates(): array
  {
    return [
      'use' => [$this->namespace . '\\' . $this->getClassName()],
      'declare' => [$this->getClassName() . '::class'],
      'provide' => [],
      'control' => [],
      'import' => [],
      'export' => [],
      'config' => [],
    ];
  }

  /**
   * @return int
   */
  public function finalizeBuild(): int
  {
    $name = new Text($this->name);
    $componentDir = dirname($this->getFilePath());
    $templateFilename = Path::join($componentDir, $name->pascalCase() . 'Component.twig');
    $stylesheetFilename = Path::join($componentDir, $name->pascalCase() . 'Component.css');

    if (! file_exists($templateFilename) ) {
      if (false === touch($templateFilename)) {
        $this->output->writeln("<error>Failed to create $templateFilename.</error>");
        return Command::FAILURE;
      }

      $bytes = file_put_contents($templateFilename, '<p>' . $name->kebabCase() . ' works!</p>');

      if (false === $bytes) {
        $this->output->writeln("<error>Failed to write to $templateFilename.</error>");
        return Command::FAILURE;
      }

      $templateFilename = ltrim(str_replace(getcwd() ?: '', '', $templateFilename), DIRECTORY_SEPARATOR);
      $this->output->writeln("<info>CREATE</info> $templateFilename ($bytes bytes)");
    }

    if (! file_exists($stylesheetFilename) ) {
      if (false === touch($stylesheetFilename)) {
        $this->output->writeln("<error>Failed to create $stylesheetFilename.</error>");
        return Command::FAILURE;
      }

      $bytes = file_put_contents($stylesheetFilename, '/* ' . $name->pascalCase() . 'Component.css */');

      if (false === $bytes) {
        $this->output->writeln("<error>Failed to write to $stylesheetFilename.</error>");
        return Command::FAILURE;
      }

      $stylesheetFilename = ltrim(str_replace(getcwd() ?: '', '', $stylesheetFilename), DIRECTORY_SEPARATOR);
      $this->output->writeln("<info>CREATE</info> $stylesheetFilename ($bytes bytes)");
    }

    return parent::finalizeBuild();
  }
}