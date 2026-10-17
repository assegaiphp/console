<?php

namespace Assegai\Console\WebComponents\Scanner;

final class WebComponentDirectoryIndex
{
  /** @var array<string, list<string>> */
  private array $entries = [];
  public int $listCount = 0;

  /** @param list<string> $directories */
  public function __construct(array $directories)
  {
    foreach ($directories as $directory) {
      $entries = $this->read($directory);
      if ($entries !== null) {
        $this->entries[$directory] = $entries;
      }
    }
  }

  /** @return list<string> Added/removed paths; no source content or timestamps are read. */
  public function refresh(): array
  {
    $changed = [];
    foreach ($this->entries as $directory => $previous) {
      $current = $this->read($directory);
      if ($current === null) {
        unset($this->entries[$directory]);
        $changed[] = $directory;
        continue;
      }
      if ($current !== $previous) {
        foreach (array_unique([...array_diff($previous, $current), ...array_diff($current, $previous)]) as $name) {
          $changed[] = $directory . '/' . $name;
        }
        $this->entries[$directory] = $current;
      }
    }
    return $changed;
  }

  /** @param list<string> $directories */
  public function includeDirectories(array $directories): void
  {
    foreach ($directories as $directory) {
      if (!array_key_exists($directory, $this->entries)) {
        $entries = $this->read($directory);
        if ($entries !== null) {
          $this->entries[$directory] = $entries;
        }
      }
    }
  }

  /** @return list<string>|null */
  private function read(string $directory): ?array
  {
    $this->listCount++;
    try {
      $iterator = new \DirectoryIterator($directory);
    } catch (\UnexpectedValueException $error) {
      clearstatcache(true, $directory);
      if (!is_dir($directory)) {
        return null;
      }
      throw $error;
    }
    $entries = [];
    foreach ($iterator as $entry) {
      if (!$entry->isDot()) {
        $entries[] = $entry->getFilename();
      }
    }
    sort($entries, SORT_STRING);
    return $entries;
  }
}
