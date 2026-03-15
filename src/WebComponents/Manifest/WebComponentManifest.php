<?php

namespace Assegai\Console\WebComponents\Manifest;

final class WebComponentManifest
{
  /**
   * @param string[] $components
   */
  public function __construct(private array $components = [])
  {
  }

  /**
   * @return array{components: string[]}
   */
  public function toArray(): array
  {
    $components = array_values(array_unique($this->components));
    sort($components);

    return ['components' => $components];
  }

  public function toJson(): string
  {
    return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: "{\n  \"components\": []\n}";
  }

  public function write(string $filename): int|false
  {
    $directory = dirname($filename);

    if (!is_dir($directory) && false === mkdir($directory, 0755, true)) {
      return false;
    }

    return file_put_contents($filename, $this->toJson());
  }
}
