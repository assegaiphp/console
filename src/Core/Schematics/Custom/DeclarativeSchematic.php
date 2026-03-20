<?php

namespace Assegai\Console\Core\Schematics\Custom;

use Symfony\Component\Console\Command\Command;

class DeclarativeSchematic extends AbstractCustomSchematic
{
  public function build(): int
  {
    $templates = $this->context()->definition->metadata['templates'] ?? [];

    if (! is_array($templates)) {
      $this->context()->output->writeln('<error>Invalid declarative schematic templates.</error>');
      return Command::FAILURE;
    }

    foreach ($templates as $template) {
      if (! is_array($template)) {
        $this->context()->output->writeln('<error>Invalid declarative template definition.</error>');
        return Command::FAILURE;
      }

      $source = $template['source'] ?? null;
      $target = $template['target'] ?? null;

      if (! is_string($source) || $source === '' || ! is_string($target) || $target === '') {
        $this->context()->output->writeln('<error>Declarative templates require both source and target values.</error>');
        return Command::FAILURE;
      }

      $content = $this->loadTemplate($source);

      if ($this->writeRelativeFile($target, $content) !== Command::SUCCESS) {
        return Command::FAILURE;
      }
    }

    return Command::SUCCESS;
  }
}
