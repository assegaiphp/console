<?php

namespace Assegai\Console\Util;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The Inspector class. This class is responsible for inspecting the workspace directory.
 *
 * @package Assegai\Console\Util
 */
class Inspector
{
  /**
   *
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function __construct(
    protected InputInterface $input,
    protected OutputInterface $output
  )
  {
  }

  /**
   * Check if the given package is installed globally.
   *
   * @param string $packageName The package name.
   * @return bool
   */
  public function packageIsInstalledGlobally(string $packageName): bool
  {
    return InstalledVersions::isInstalled($packageName);
  }

  /**
   * Check if the given path is a valid project directory.
   *
   * @param string $workspaceDirectory The path to check.
   * @return bool
   */
  public function isValidWorkspace(string $workspaceDirectory): bool
  {
    if (! is_dir($workspaceDirectory) )
    {
      $this->output->writeln("Workspace $workspaceDirectory does not exist.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    $workspaceFiles = scandir($workspaceDirectory);

    if (false === $workspaceFiles)
    {
      $this->output->writeln("Failed to read workspace $workspaceDirectory.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    if (count($workspaceFiles) === 2)
    {
      $this->output->writeln("Workspace $workspaceDirectory is empty.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    if (! file_exists(Path::join($workspaceDirectory, 'composer.json')))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have a composer.json file.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    if (! file_exists(Path::join($workspaceDirectory, 'assegai.json')))
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have an assegai.json file.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    $bootstrapFilename = BOOTSTRAP_FILE;
    if (! file_exists(Path::join($workspaceDirectory, $bootstrapFilename)) )
    {
      $this->output->writeln("Workspace $workspaceDirectory does not have a $bootstrapFilename file.", OutputInterface::VERBOSITY_VERBOSE);
      return false;
    }

    return true;
  }

  /**
   * Check if the given path is not a valid project directory.
   *
   * @param string $workspaceDirectory The path to check.
   * @return bool
   */
  public function isNotAValidWorkspace(string $workspaceDirectory): bool
  {
    return ! $this->isValidWorkspace($workspaceDirectory);
  }

  /**
   * Get the version of the CLI package.
   *
   * @return string
   */
  public function getCLIVersion(): string
  {
    return self::getRunningCLIVersion();
  }

  /**
   * Get the version of the CLI package that is currently running.
   *
   * @return string
   */
  public static function getRunningCLIVersion(): string
  {
    return self::getRuntimePackageVersion(PACKAGE_NAME_CLI) ?? 'Unknown';
  }

  /**
   * Get the version of the installed framework.
   *
   * @param string|null $workingDirectory The workspace directory.
   * @return string
   */
  public function getInstalledFrameworkVersion(?string $workingDirectory = null): string
  {
    $workingDirectory = $workingDirectory ?? getcwd();
    if (false === $workingDirectory) {
      return 'Not installed';
    }

    return $this->getInstalledFrameworkVersionInfo($workingDirectory)['version'] ?? 'Not installed';
  }

  /**
   * Get the version information of the installed framework.
   *
   * @param string|null $workingDirectory
   * @return array{version: ?string, source: ?string}
   */
  public function getInstalledFrameworkVersionInfo(?string $workingDirectory = null): array
  {
    $workingDirectory = $workingDirectory ?? getcwd();

    if (false === $workingDirectory) {
      return [
        'version' => null,
        'source' => null,
      ];
    }

    return $this->getWorkspacePackageVersionInfo(PACKAGE_NAME_CORE, $workingDirectory);
  }

  /**
   * Get the version of the given package.
   *
   * @param string $packageName The package name.
   * @param string|null $workspace The workspace directory.
   * @param bool $isGlobal Whether the package is installed globally.
   * @return false|string
   */
  public function getPackageVersion(
    string $packageName,
    ?string $workspace = null,
    bool $isGlobal = false
  ): false|string
  {
    if ($isGlobal || null === $workspace) {
      return self::getRuntimePackageVersion($packageName) ?: false;
    }

    return $this->getWorkspacePackageVersionInfo($packageName, $workspace)['version'] ?? false;
  }

  /**
   * @return array{version: ?string, source: ?string}
   */
  public function getWorkspacePackageVersionInfo(string $packageName, string $workspace): array
  {
    $workspace = Path::normalize($workspace);

    $installedJsonPath = Path::join($workspace, 'vendor', 'composer', 'installed.json');
    $installedJson = $this->readJsonFile($installedJsonPath);
    $installedJsonVersion = $this->extractVersionFromInstalledJson($installedJson, $packageName);

    if (null !== $installedJsonVersion) {
      return [
        'version' => $installedJsonVersion,
        'source' => 'installed',
      ];
    }

    $installedPhpPath = Path::join($workspace, 'vendor', 'composer', 'installed.php');
    $installedPhpVersion = $this->extractVersionFromInstalledPhp($installedPhpPath, $packageName);

    if (null !== $installedPhpVersion) {
      return [
        'version' => $installedPhpVersion,
        'source' => 'installed',
      ];
    }

    $composerLockPath = Path::join($workspace, 'composer.lock');
    $composerLock = $this->readJsonFile($composerLockPath);
    $lockedVersion = $this->extractVersionFromComposerLock($composerLock, $packageName);

    if (null !== $lockedVersion) {
      return [
        'version' => $lockedVersion,
        'source' => 'lock',
      ];
    }

    return [
      'version' => null,
      'source' => null,
    ];
  }

  private static function getRuntimePackageVersion(string $packageName): ?string
  {
    try {
      if (InstalledVersions::isInstalled($packageName)) {
        return InstalledVersions::getPrettyVersion($packageName)
          ?? InstalledVersions::getVersion($packageName);
      }
    } catch (OutOfBoundsException) {
      return null;
    }

    $rootPackage = InstalledVersions::getRootPackage();

    if ($rootPackage['name'] !== $packageName) {
      return null;
    }

    return $rootPackage['pretty_version'];
  }

  /**
   * @return array<string, mixed>|null
   */
  private function readJsonFile(string $path): ?array
  {
    if (!is_file($path)) {
      return null;
    }

    $contents = file_get_contents($path);

    if (false === $contents) {
      return null;
    }

    $decoded = json_decode($contents, true);

    return is_array($decoded) ? $decoded : null;
  }

  private function extractVersionFromInstalledPhp(string $path, string $packageName): ?string
  {
    if (!is_file($path)) {
      return null;
    }

    /** @var mixed $installed */
    $installed = include $path;

    if (!is_array($installed)) {
      return null;
    }

    if (isset($installed['versions']) && is_array($installed['versions'])) {
      return $this->extractVersionFromPackageMap($installed['versions'], $packageName);
    }

    foreach ($installed as $packageSet) {
      if (!is_array($packageSet)) {
        continue;
      }

      if (!isset($packageSet['versions']) || !is_array($packageSet['versions'])) {
        continue;
      }

      $version = $this->extractVersionFromPackageMap($packageSet['versions'], $packageName);

      if (null !== $version) {
        return $version;
      }
    }

    return null;
  }

  /**
   * @param array<string, mixed>|null $installed
   */
  private function extractVersionFromInstalledJson(?array $installed, string $packageName): ?string
  {
    if (null === $installed) {
      return null;
    }

    $packages = $installed['packages'] ?? $installed;

    if (!is_array($packages)) {
      return null;
    }

    foreach ($packages as $package) {
      if (!is_array($package)) {
        continue;
      }

      if (($package['name'] ?? null) !== $packageName) {
        continue;
      }

      $version = $package['version'] ?? $package['pretty_version'] ?? null;
      return is_string($version) ? $version : null;
    }

    return null;
  }

  /**
   * @param array<string, mixed>|null $composerLock
   */
  private function extractVersionFromComposerLock(?array $composerLock, string $packageName): ?string
  {
    if (null === $composerLock) {
      return null;
    }

    $packageGroups = [
      $composerLock['packages'] ?? [],
      $composerLock['packages-dev'] ?? [],
    ];

    foreach ($packageGroups as $packages) {
      if (!is_array($packages)) {
        continue;
      }

      foreach ($packages as $package) {
        if (!is_array($package)) {
          continue;
        }

        if (($package['name'] ?? null) !== $packageName) {
          continue;
        }

        $version = $package['version'] ?? null;
        return is_string($version) ? $version : null;
      }
    }

    return null;
  }

  /**
   * @param array<string, mixed> $packages
   */
  private function extractVersionFromPackageMap(array $packages, string $packageName): ?string
  {
    $package = $packages[$packageName] ?? null;

    if (!is_array($package)) {
      return null;
    }

    $version = $package['pretty_version'] ?? $package['version'] ?? null;

    return is_string($version) ? $version : null;
  }
}
