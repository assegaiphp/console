<?php

declare(strict_types=1);

namespace Assegai\Console\Commands;

use Assegai\Console\Releases\ReleaseInventoryValidator;
use Assegai\Console\Releases\ReleaseStage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'release:check', description: 'Read-only validation of a coordinated release inventory and owner evidence.')]
final class ReleaseCheck extends Command
{
  protected function configure(): void
  {
    $this->addOption('inventory', null, InputOption::VALUE_REQUIRED, 'The explicit reviewed release inventory JSON file.')
      ->addOption('modules', null, InputOption::VALUE_REQUIRED, 'The explicit canonical module membership JSON file.')
      ->addOption('stage', null, InputOption::VALUE_REQUIRED, 'The verification stage: prepare or publish.', 'prepare')
      ->addOption('evidence', null, InputOption::VALUE_REQUIRED, 'The generated owner evidence JSON file.')
      ->addOption('website-manifest', null, InputOption::VALUE_REQUIRED, 'Publish: the actual Website composer.json file.')
      ->addOption('website-lock', null, InputOption::VALUE_REQUIRED, 'Publish: the actual Website composer.lock file.')
      ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format.', 'json');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $inventory = $input->getOption('inventory');
    $modules = $input->getOption('modules');
    $evidence = $input->getOption('evidence');
    $websiteManifest = $input->getOption('website-manifest');
    $websiteLock = $input->getOption('website-lock');
    $stageValue = $input->getOption('stage');
    $stage = is_string($stageValue) ? ReleaseStage::tryFrom($stageValue) : null;
    if (!is_string($inventory) || !is_string($modules) || ($evidence !== null && !is_string($evidence))
      || ($websiteManifest !== null && !is_string($websiteManifest)) || ($websiteLock !== null && !is_string($websiteLock))
      || $stage === null || $input->getOption('format') !== 'json') {
      $output->writeln(json_encode(['schema' => 1, 'status' => 'blocked', 'issues' => [
        ['code' => 'invalid-options', 'path' => 'options', 'message' => 'Explicit --inventory and --modules files, a valid stage and --format=json are required.'],
      ]], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
      return self::FAILURE;
    }
    $result = (new ReleaseInventoryValidator())->validateFiles($inventory, $modules, $stage, $evidence, $websiteManifest, $websiteLock);
    $output->writeln(json_encode($result->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
    return $result->isReady ? self::SUCCESS : self::FAILURE;
  }
}
