<?php

namespace Assegai\Console\WebComponents\HotReload;

use Assegai\Console\Util\Path;
use Assegai\Console\WebComponents\WebComponentConfig;

final class WebComponentHotReloadState
{
  private ?string $lastVersion = null;
  private float $lastWriteAt = 0.0;

  public function __construct(private readonly string $workspace)
  {
  }

  public function activate(): bool
  {
    $filename = $this->getFilename();
    $directory = dirname($filename);

    if (!is_dir($directory) && false === mkdir($directory, 0755, true)) {
      return false;
    }

    $this->lastVersion = $this->resolveBundleVersion() ?? $this->makeVersion();

    return $this->writeState($this->lastVersion);
  }

  public function synchronize(): bool
  {
    $version = $this->resolveBundleVersion() ?? $this->lastVersion ?? $this->makeVersion();
    $heartbeatSeconds = max(0.25, WebComponentConfig::getHotReloadPollInterval($this->workspace) / 1000);

    if ($version === $this->lastVersion && (microtime(true) - $this->lastWriteAt) < $heartbeatSeconds) {
      return true;
    }

    $this->lastVersion = $version;

    return $this->writeState($version);
  }

  public function deactivate(): void
  {
    $filename = $this->getFilename();

    if (is_file($filename)) {
      unlink($filename);
    }
  }

  private function getFilename(): string
  {
    return Path::join($this->workspace, WebComponentConfig::getHotReloadPath($this->workspace));
  }

  private function getBundleUrl(): string
  {
    $path = '/' . ltrim(WebComponentConfig::getOutputPath($this->workspace), '/');

    if (str_starts_with($path, '/public/')) {
      return substr($path, strlen('/public'));
    }

    return $path;
  }

  private function writeState(string $version): bool
  {
    $filename = $this->getFilename();
    $existingState = $this->readState();
    $payload = [
      'active' => true,
      'bundleUrl' => $this->getBundleUrl(),
      'interval' => WebComponentConfig::getHotReloadPollInterval($this->workspace),
      'version' => $version,
      'createdAt' => $existingState['createdAt'] ?? gmdate(DATE_ATOM),
      'updatedAt' => gmdate(DATE_ATOM),
      'expiresAt' => gmdate(DATE_ATOM, time() + WebComponentConfig::getHotReloadTtl($this->workspace)),
    ];

    $written = file_put_contents(
      $filename,
      json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    if (false !== $written) {
      $this->lastWriteAt = microtime(true);
      return true;
    }

    return false;
  }

  /**
   * @return array<string, mixed>|null
   */
  private function readState(): ?array
  {
    $filename = $this->getFilename();

    if (!is_file($filename)) {
      return null;
    }

    $contents = file_get_contents($filename);

    if (!$contents) {
      return null;
    }

    $state = json_decode($contents, true);

    return is_array($state) ? $state : null;
  }

  private function resolveBundleVersion(): ?string
  {
    $bundleFilename = Path::join($this->workspace, WebComponentConfig::getOutputPath($this->workspace));

    if (!is_file($bundleFilename)) {
      return null;
    }

    $hash = hash_file('sha1', $bundleFilename);

    return is_string($hash) && $hash !== ''
      ? $hash
      : null;
  }

  private function makeVersion(): string
  {
    return sha1((string)microtime(true));
  }
}
