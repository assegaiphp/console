<?php

namespace Assegai\Console\Util;

use Closure;
use JsonException;

final class CliVersionChecker
{
  private const METADATA_URL = 'https://repo.packagist.org/p2/assegaiphp/console.json';
  private const SUCCESS_CACHE_TTL = 86400;
  private const FAILURE_CACHE_TTL = 3600;

  /** @var Closure(string): (false|string) */
  private Closure $fetcher;

  /** @var Closure(): int */
  private Closure $clock;

  /**
   * @param (callable(string): (false|string))|null $fetcher
   * @param callable(): int|null $clock
   */
  public function __construct(
    private readonly string $currentVersion,
    private readonly ?string $cacheFile = null,
    ?callable $fetcher = null,
    ?callable $clock = null,
  ) {
    $this->fetcher = $fetcher === null
      ? $this->fetchMetadata(...)
      : Closure::fromCallable($fetcher);
    $this->clock = $clock === null
      ? time(...)
      : Closure::fromCallable($clock);
  }

  public static function forRunningCli(): self
  {
    return new self(Inspector::getRunningCLIVersion());
  }

  public function getCurrentVersion(): string
  {
    return $this->currentVersion;
  }

  public function getLatestVersion(bool $force = false): ?string
  {
    if (! $force && ($cachedVersion = $this->readFreshCache()) !== false) {
      return $cachedVersion;
    }

    $payload = ($this->fetcher)(self::METADATA_URL);

    if (! is_string($payload)) {
      $this->writeCache(null, true);
      return null;
    }

    $latestVersion = $this->extractLatestStableVersion($payload);
    $this->writeCache($latestVersion, $latestVersion === null);

    return $latestVersion;
  }

  public function findAvailableUpdate(bool $force = false): ?string
  {
    $currentVersion = self::normalizeStableVersion($this->currentVersion);

    if ($currentVersion === null) {
      return null;
    }

    $latestVersion = $this->getLatestVersion($force);

    if ($latestVersion === null || version_compare($latestVersion, $currentVersion, '<=')) {
      return null;
    }

    return $latestVersion;
  }

  public function clearCache(): void
  {
    $cacheFile = $this->resolveCacheFile();

    if (is_file($cacheFile)) {
      @unlink($cacheFile);
    }
  }

  private function fetchMetadata(string $url): false|string
  {
    $context = stream_context_create([
      'http' => [
        'timeout' => 1.5,
        'ignore_errors' => true,
        'header' => implode("\r\n", [
          'Accept: application/json',
          sprintf('User-Agent: Assegai-CLI/%s', $this->currentVersion),
        ]),
      ],
    ]);

    return @file_get_contents($url, false, $context);
  }

  private function extractLatestStableVersion(string $payload): ?string
  {
    try {
      $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      return null;
    }

    if (! is_array($decoded)) {
      return null;
    }

    $packages = $decoded['packages'] ?? null;

    if (! is_array($packages)) {
      return null;
    }

    $releases = $packages[PACKAGE_NAME_CLI] ?? null;

    if (! is_array($releases)) {
      return null;
    }

    $latestVersion = null;

    foreach ($releases as $release) {
      if (! is_array($release)) {
        continue;
      }

      $version = $release['version'] ?? null;

      if (! is_string($version)) {
        continue;
      }

      $version = self::normalizeStableVersion($version);

      if ($version !== null && ($latestVersion === null || version_compare($version, $latestVersion, '>'))) {
        $latestVersion = $version;
      }
    }

    return $latestVersion;
  }

  /**
   * @return false|string|null False means no fresh cache entry; null is a cached failed check.
   */
  private function readFreshCache(): false|string|null
  {
    $cacheFile = $this->resolveCacheFile();

    if (! is_file($cacheFile)) {
      return false;
    }

    $contents = @file_get_contents($cacheFile);

    if (! is_string($contents)) {
      return false;
    }

    try {
      $cache = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      return false;
    }

    if (! is_array($cache) || ! is_int($cache['checked_at'] ?? null)) {
      return false;
    }

    $failed = ($cache['failed'] ?? false) === true;
    $timeToLive = $failed ? self::FAILURE_CACHE_TTL : self::SUCCESS_CACHE_TTL;

    if (($this->clock)() - $cache['checked_at'] >= $timeToLive) {
      return false;
    }

    if ($failed) {
      return null;
    }

    $version = $cache['latest_version'] ?? null;

    return is_string($version) ? self::normalizeStableVersion($version) ?? false : false;
  }

  private function writeCache(?string $latestVersion, bool $failed): void
  {
    $cacheFile = $this->resolveCacheFile();
    $cacheDirectory = dirname($cacheFile);

    if (! is_dir($cacheDirectory) && ! @mkdir($cacheDirectory, 0755, true) && ! is_dir($cacheDirectory)) {
      return;
    }

    $payload = json_encode([
      'checked_at' => ($this->clock)(),
      'latest_version' => $latestVersion,
      'failed' => $failed,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (is_string($payload)) {
      @file_put_contents($cacheFile, $payload . "\n", LOCK_EX);
    }
  }

  private function resolveCacheFile(): string
  {
    if (is_string($this->cacheFile) && trim($this->cacheFile) !== '') {
      return $this->cacheFile;
    }

    $xdgCache = getenv('XDG_CACHE_HOME');

    if (is_string($xdgCache) && trim($xdgCache) !== '') {
      return Path::join($xdgCache, 'assegai', 'version-check.json');
    }

    $home = getenv('HOME');

    if (is_string($home) && trim($home) !== '') {
      return Path::join($home, '.cache', 'assegai', 'version-check.json');
    }

    $localAppData = getenv('LOCALAPPDATA');

    if (is_string($localAppData) && trim($localAppData) !== '') {
      return Path::join($localAppData, 'Assegai', 'version-check.json');
    }

    return Path::join(sys_get_temp_dir(), 'assegai-' . substr(hash('sha256', __FILE__), 0, 12), 'version-check.json');
  }

  private static function normalizeStableVersion(string $version): ?string
  {
    $version = ltrim(trim($version), 'vV');

    return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1 ? $version : null;
  }
}
