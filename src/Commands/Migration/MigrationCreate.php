<?php

namespace Assegai\Console\Commands\Migration;

use Assegai\Console\Util\Text;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MigrationCreate. This class is a command that creates a new migration.
 *
 * @package Assegai\Console\Commands\Migration
 */
#[AsCommand(
    name: 'migration:create',
    description: 'Create a new migration',
    aliases: ['migrate:create']
)]
class MigrationCreate extends Command
{
  /**
   * @inheritDoc
   */
  public function configure(): void
  {
    $this
      ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration');
  }

  /**
   * @inheritDoc
   */
  public function execute(InputInterface $input, OutputInterface $output): int
  {
    // TODO: Implement the create logic

    return Command::SUCCESS;
  }

  /**
   * Get the migration name
   *
   * @param string $name
   * @return string
   */
  public function getMigrationName(string $name): string
  {
    $timestamp = date('YmdHis');
    $name = new Text($name);
    return $timestamp . '_' . $name->snakeCase();
  }
}