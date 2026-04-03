<?php

namespace Assegai\Console\Commands\Updates;

use Assegai\Console\Util\Path;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'updates:scaffold',
  description: 'Scaffolds a release update-advisor entry and upgrade-notes draft.'
)]
class ScaffoldUpdateGuide extends Command
{
  protected function configure(): void
  {
    $this
      ->addArgument('from', InputArgument::REQUIRED, 'The version users are upgrading from.')
      ->addArgument('to', InputArgument::REQUIRED, 'The version users are upgrading to.')
      ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'The Assegai monorepo root.', getcwd())
      ->addOption('title', null, InputOption::VALUE_REQUIRED, 'The guide title.')
      ->addOption('eyebrow', null, InputOption::VALUE_REQUIRED, 'The guide eyebrow.', 'Release Upgrade')
      ->addOption('status-label', null, InputOption::VALUE_REQUIRED, 'The status label.', 'Planned Release')
      ->addOption('summary', null, InputOption::VALUE_REQUIRED, 'A short release summary.')
      ->addOption('warning', null, InputOption::VALUE_REQUIRED, 'A release warning or caveat.', 'Review the release notes and fill in the upgrade details before publishing this entry.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $root = Path::normalize((string) ($input->getOption('directory') ?: getcwd() ?: '.'));
    $from = trim((string) $input->getArgument('from'));
    $to = trim((string) $input->getArgument('to'));

    if ($from === '' || $to === '') {
      $output->writeln('<error>Both from and to versions are required.</error>');
      return Command::FAILURE;
    }

    $upgradeDirectory = Path::join($root, 'website', 'src', 'Update', 'Data', 'upgrades');
    $releaseNotesDirectory = Path::join($root, 'core', 'docs', 'releases');

    if (!is_dir($upgradeDirectory) && !mkdir($upgradeDirectory, 0755, true) && !is_dir($upgradeDirectory)) {
      throw new RuntimeException('Failed to create update advisor upgrades directory.');
    }

    if (!is_dir($releaseNotesDirectory) && !mkdir($releaseNotesDirectory, 0755, true) && !is_dir($releaseNotesDirectory)) {
      throw new RuntimeException('Failed to create releases docs directory.');
    }

    $filenameSlug = $this->slugify($from) . '-to-' . $this->slugify($to);
    $upgradeFilename = Path::join($upgradeDirectory, $filenameSlug . '.php');
    $notesFilename = Path::join($releaseNotesDirectory, $to . '-upgrade-notes-draft.md');

    if (!file_exists($upgradeFilename)) {
      file_put_contents($upgradeFilename, $this->buildUpgradeEntryTemplate(
        from: $from,
        to: $to,
        title: (string) ($input->getOption('title') ?: sprintf('Upgrade from %s to %s', $from, $to)),
        eyebrow: (string) $input->getOption('eyebrow'),
        statusLabel: (string) $input->getOption('status-label'),
        summary: (string) ($input->getOption('summary') ?: sprintf('%s introduces meaningful framework changes. Fill in the automatic and manual steps before publishing this release.', $to)),
        warning: (string) $input->getOption('warning'),
      ));
      $output->writeln(sprintf('<question>CREATE</question> %s', $upgradeFilename));
    } else {
      $output->writeln(sprintf('<comment>SKIP</comment> %s already exists', $upgradeFilename));
    }

    if (!file_exists($notesFilename)) {
      file_put_contents($notesFilename, $this->buildUpgradeNotesTemplate($from, $to));
      $output->writeln(sprintf('<question>CREATE</question> %s', $notesFilename));
    } else {
      $output->writeln(sprintf('<comment>SKIP</comment> %s already exists', $notesFilename));
    }

    return Command::SUCCESS;
  }

  private function slugify(string $value): string
  {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9.]+/', '-', $value) ?? $value;

    return trim($value, '-');
  }

  private function buildUpgradeEntryTemplate(string $from, string $to, string $title, string $eyebrow, string $statusLabel, string $summary, string $warning): string
  {
    $template = [
      'from' => $from,
      'to' => $to,
      'eyebrow' => $eyebrow,
      'title' => $title,
      'summary' => $summary,
      'statusLabel' => $statusLabel,
      'warning' => $warning,
      'commandPreview' => 'assegai update',
      'automaticSteps' => [
        'Run `assegai update` from the project root.',
      ],
      'manualSteps' => [
        'all' => [
          'Fill in the manual upgrade steps for this release.',
        ],
        'medium' => [],
        'advanced' => [],
        'flags' => [
          'orm' => [],
          'events' => [],
          'openswoole' => [],
          'windows' => [],
        ],
      ],
      'verificationSteps' => [
        'all' => [
          'Describe the verification steps required before users call the upgrade done.',
        ],
        'medium' => [],
        'advanced' => [],
        'flags' => [
          'orm' => [],
          'events' => [],
          'openswoole' => [],
          'windows' => [],
        ],
      ],
      'docs' => [],
    ];

    return "<?php\n\nreturn " . var_export($template, true) . ";\n";
  }

  private function buildUpgradeNotesTemplate(string $from, string $to): string
  {
    return <<<MD
# {$to} Upgrade Notes Draft

## Path

- From: `{$from}`
- To: `{$to}`

## Automatic

- [ ] Describe what `assegai update` handles automatically.

## Manual

- [ ] Describe the manual project changes users still need to make.

## Verification

- [ ] Describe the checks users should run before they call the upgrade complete.
MD;
  }
}
