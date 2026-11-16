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
      ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'An existing caller-owned draft directory outside source repositories and Composer projects.')
      ->addOption('title', null, InputOption::VALUE_REQUIRED, 'The guide title.')
      ->addOption('eyebrow', null, InputOption::VALUE_REQUIRED, 'The guide eyebrow.', 'Release Upgrade')
      ->addOption('status-label', null, InputOption::VALUE_REQUIRED, 'The status label.', 'Planned Release')
      ->addOption('summary', null, InputOption::VALUE_REQUIRED, 'A short release summary.')
      ->addOption('warning', null, InputOption::VALUE_REQUIRED, 'A release warning or caveat.', 'Review the release notes and fill in the upgrade details before publishing this entry.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $requestedOutput = $input->getOption('output');
    $root = is_string($requestedOutput) && $requestedOutput !== '' ? realpath($requestedOutput) : false;
    $from = trim((string) $input->getArgument('from'));
    $to = trim((string) $input->getArgument('to'));

    if ($from === '' || $to === '' || $this->slugify($from) === '' || $this->slugify($to) === '') {
      $output->writeln('<error>Both from and to versions must produce nonempty draft names.</error>');
      return Command::FAILURE;
    }

    if ($root === false || !is_dir($root) || !is_writable($root) || !$this->isDraftDirectory($root)) {
      $output->writeln('<error>Please supply --output with an existing writable directory outside source repositories and Composer projects. Drafts must be reviewed by the Website owner before admission.</error>');
      return Command::FAILURE;
    }

    $filenameSlug = $this->slugify($from) . '-to-' . $this->slugify($to);
    $upgradeFilename = Path::join($root, $filenameSlug . '.php');
    $notesFilename = Path::join($root, $this->slugify($to) . '-upgrade-notes-draft.md');

    if (file_exists($upgradeFilename) || is_link($upgradeFilename) || file_exists($notesFilename) || is_link($notesFilename)) {
      $output->writeln('<error>A draft target already exists. Please choose another output directory.</error>');
      return Command::FAILURE;
    }

    $created = [];
    try {
      $entry = $this->buildUpgradeEntryTemplate(
        from: $from,
        to: $to,
        title: (string) ($input->getOption('title') ?: sprintf('Upgrade from %s to %s', $from, $to)),
        eyebrow: (string) $input->getOption('eyebrow'),
        statusLabel: (string) $input->getOption('status-label'),
        summary: (string) ($input->getOption('summary') ?: sprintf('%s introduces meaningful framework changes. Fill in the automatic and manual steps before publishing this release.', $to)),
        warning: (string) $input->getOption('warning'),
      );
      foreach ([$upgradeFilename => $entry, $notesFilename => $this->buildUpgradeNotesTemplate($from, $to)] as $filename => $contents) {
        $handle = @fopen($filename, 'xb');
        if ($handle === false) {
          throw new RuntimeException('Failed to create a new draft target.');
        }
        $created[] = $filename;
        try {
          if (fwrite($handle, $contents) !== strlen($contents)) {
            throw new RuntimeException('Failed to write a complete draft.');
          }
        } finally {
          fclose($handle);
        }
      }
    } catch (RuntimeException $error) {
      foreach ($created as $filename) { unlink($filename); }
      $output->writeln('<error>' . $error->getMessage() . '</error>');
      return Command::FAILURE;
    }
    foreach ($created as $filename) { $output->writeln(sprintf('<question>CREATE</question> %s', $filename)); }
    $output->writeln('<comment>These are incomplete drafts. The Website owner must review and admit them through the coordinated release workflow.</comment>');

    return Command::SUCCESS;
  }

  private function isDraftDirectory(string $directory): bool
  {
    do {
      if (file_exists($directory . '/.git') || file_exists($directory . '/composer.json')) {
        return false;
      }
      $parent = dirname($directory);
      if ($parent === $directory) { return true; }
      $directory = $parent;
    } while (true);
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
