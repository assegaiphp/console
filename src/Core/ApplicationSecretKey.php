<?php

namespace Assegai\Console\Core;

use Assegai\Console\Util\Path;
use RuntimeException;

/** Generates application keys without evaluating or rewriting other dotenv settings. */
final class ApplicationSecretKey
{
  /** @var array{offset: int, length: int, value: string}|null */
  private ?array $assignment = null;

  private function __construct(
    private readonly string $filename,
    private readonly ?string $originalContents,
    private readonly string $contents,
  ) {
    // Consume complete quoted values, including multiline values, so text inside
    // another variable cannot be mistaken for an APP_SECRET_KEY assignment.
    $pattern = <<<'REGEX'
~^[\t ]*(?:export[\t ]+)?(?<name>[A-Za-z_][A-Za-z0-9_]*)[\t ]*=[\t ]*(?<value>"(?:\\[\s\S]|[^"\\])*"|'(?:\\[\s\S]|[^'\\])*'|[^\r\n]*)(?:[\t ]*(?:\#[^\r\n]*)?)(?=\r?$)~m
REGEX;
    if (preg_match_all($pattern, $contents, $entries, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
      throw new RuntimeException('Could not read dotenv assignments. No files were changed.');
    }

    foreach ($entries as $entry) {
      $rawValue = $entry['value'][0];
      $quote = $rawValue[0] ?? '';
      if ($quote === '"' || $quote === "'") {
        if (strlen($rawValue) < 2 || ! str_ends_with($rawValue, $quote)) {
          throw new RuntimeException('An unterminated quoted dotenv value must be corrected before generating a key.');
        }
        $value = substr($rawValue, 1, -1);
      } else {
        $rawValue = rtrim(explode('#', $rawValue, 2)[0], " \t");
        $value = $rawValue;
      }

      if ($entry['name'][0] !== 'APP_SECRET_KEY') {
        continue;
      }
      if ($this->assignment !== null) {
        throw new RuntimeException('Multiple APP_SECRET_KEY assignments found. Keep a single assignment and retry.');
      }

      $this->assignment = [
        'offset' => $entry['value'][1],
        'length' => strlen($rawValue),
        'value' => $value,
      ];
    }
  }

  public static function forWorkspace(string $workspace): self
  {
    $filename = Path::join($workspace, '.env');
    if (is_link($filename)) {
      throw new RuntimeException('.env is a symbolic link. Generate and manage the key in its target environment.');
    }

    $exists = file_exists($filename);
    $source = $exists ? $filename : Path::join($workspace, '.env.example');
    if (! is_file($source) || ! is_readable($source)) {
      throw new RuntimeException($exists
        ? 'Cannot read .env.'
        : 'No readable .env or .env.example found. Create .env and run assegai key:generate again.');
    }

    $contents = file_get_contents($source);
    if ($contents === false) {
      throw new RuntimeException('Failed to read the environment file.');
    }

    return new self($filename, $exists ? $contents : null, $contents);
  }

  public function createsEnvironmentFile(): bool
  {
    return $this->originalContents === null;
  }

  public function replacesExistingKey(): bool
  {
    return ! $this->createsEnvironmentFile() && ! in_array($this->assignment['value'] ?? '', [
      '',
      'your_secret_key_here',
      'your-secret-key',
      '[YOUR_SECRET_KEY]',
    ], true);
  }

  public function generate(): void
  {
    $key = bin2hex(random_bytes(32));
    if ($this->assignment !== null) {
      $contents = substr_replace($this->contents, $key, $this->assignment['offset'], $this->assignment['length']);
    } else {
      $newline = str_contains($this->contents, "\r\n") ? "\r\n" : "\n";
      $separator = $this->contents === '' || str_ends_with($this->contents, "\n") ? '' : $newline;
      $contents = $this->contents . $separator . 'APP_SECRET_KEY=' . $key . $newline;
    }

    $directory = dirname($this->filename);
    if (! is_writable($directory) || ($this->originalContents !== null && ! is_writable($this->filename))) {
      throw new RuntimeException('Cannot write .env. Check the file and directory permissions.');
    }

    // Stage the complete file with private permissions, then replace it only once
    // every write has succeeded. A failed write must not truncate an existing key.
    $temporary = tempnam($directory, '.assegai-env-');
    if ($temporary === false) {
      throw new RuntimeException('Could not prepare the environment file.');
    }

    try {
      if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
        throw new RuntimeException('Failed to write the application key.');
      }
      $permissions = $this->originalContents === null ? 0600 : fileperms($this->filename);
      if ($permissions === false || ! chmod($temporary, $permissions & 0777)) {
        throw new RuntimeException('Failed to set environment file permissions.');
      }
      if (is_link($this->filename) || ($this->originalContents === null
        ? file_exists($this->filename)
        : ! is_file($this->filename) || file_get_contents($this->filename) !== $this->originalContents)) {
        throw new RuntimeException('.env changed while generating the key. Run the command again.');
      }
      if (! rename($temporary, $this->filename)) {
        throw new RuntimeException('Failed to save the application key.');
      }
    } finally {
      if (is_file($temporary)) {
        unlink($temporary);
      }
    }
  }
}
