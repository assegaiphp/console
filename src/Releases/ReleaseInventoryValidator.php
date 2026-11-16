<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

use Assegai\Console\Util\ComposerManifest;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use JsonException;
use RuntimeException;
use stdClass;

/** One read-only validator for Core's reviewed inventory and generated owner evidence. */
final class ReleaseInventoryValidator
{
  private const string LINE = '/\A(0|[1-9][0-9]{0,8})\.(0|[1-9][0-9]{0,8})\z/';

  public function validateFiles(string $inventoryPath, string $modulesPath, ReleaseStage $stage = ReleaseStage::Prepare, ?string $evidencePath = null, ?string $websiteManifestPath = null, ?string $websiteLockPath = null): ReleaseValidationResult
  {
    $inventory = null;
    $inventoryHash = $modulesHash = null;
    $issues = [];
    try {
      [$inventory, $inventoryHash, $modulesHash, $canonical] = $this->readInventory($inventoryPath, $modulesPath, $issues);
      if ($evidencePath === null) {
        $issues[] = self::issue('missing-evidence', 'evidence', 'Generated source, dependency, package and Website evidence is required.');
      } else {
        [$evidence] = $this->read($evidencePath);
        (new ReleaseEvidenceValidator())->validate($inventory, $stage, $inventoryHash, $modulesHash, $canonical, $evidence, $issues);
        if ($stage === ReleaseStage::Publish) {
          if ($websiteManifestPath === null || $websiteLockPath === null) {
            $issues[] = self::issue('website-dependencies', 'website', 'Publish verification requires explicit actual Website manifest and lock files.');
          } else {
            $website = self::map($evidence['website'] ?? null);
            $consumers = self::map($canonical['consumers']);
            $this->websiteDependencies($inventory, ComposerObservation::fromFiles($websiteManifestPath, $websiteLockPath), array_key_first($consumers), $website['sourceSha'] ?? null, $website['composerLockSha256'] ?? null, $issues);
          }
        }
      }
    } catch (RuntimeException | JsonException $error) {
      $issues[] = self::issue('invalid-input', 'inputs', $error->getMessage());
    }
    return new ReleaseValidationResult($stage, $inventory, $inventoryHash, $modulesHash, $issues);
  }

  /** @param list<array{code: string, path: string, message: string}> $issues */
  private function websiteDependencies(ReleaseInventory $inventory, ComposerObservation $observation, ?string $consumer, mixed $sourceSha, mixed $lockHash, array &$issues): void
  {
    if ($observation->name !== $consumer || $observation->sourceSha !== $sourceSha || $observation->lockSha256 !== $lockHash || !isset($observation->versions[ReleaseInventory::CORE_PACKAGE], $observation->require[ReleaseInventory::CORE_PACKAGE])) {
      $issues[] = self::issue('website-dependencies', 'website', 'The actual Website package and runtime Core dependency must match its recorded lock identity.');
    }
    foreach ($observation->versions as $name => $version) {
      $plan = $inventory->releasedPackages()[$name] ?? null;
      if ($plan === null || !self::versionAllowed($version, $plan['installConstraint'])) {
        $issues[] = self::issue('website-dependencies', 'website.lock.' . $name, 'Actual Website locked stable versions must satisfy the reviewed compatibility envelope.');
      }
    }
    foreach (['require' => $observation->require, 'require-dev' => $observation->requireDev] as $section => $requirements) {
      foreach ($requirements as $name => $constraint) {
        if (!str_starts_with($name, 'assegaiphp/')) { continue; }
        $plan = $inventory->releasedPackages()[$name] ?? null;
        $line = $inventory->packageReleaseLines[$name] ?? null;
        if ($plan === null || $line === null || !is_string($constraint) || !isset($observation->versions[$name])) {
          $issues[] = self::issue('website-dependencies', 'website.' . $section . '.' . $name, 'Website first-party requirements must identify released, actually locked modules.');
          continue;
        }
        foreach (self::rangeIssues($constraint) as $message) {
          $issues[] = self::issue('website-dependencies', 'website.' . $section . '.' . $name, $message);
        }
        if (!self::versionAllowed($observation->versions[$name], $constraint)) {
          $issues[] = self::issue('website-dependencies', 'website.' . $section . '.' . $name, 'The actual locked version must also satisfy its actual Website requirement.');
        }
      }
    }
  }

  /** Loads validated recommendation data; it does not establish release readiness. */
  public function loadInventory(string $inventoryPath, string $modulesPath, ?string $expectedInventoryHash = null, ?string $expectedModulesHash = null): ReleaseInventory
  {
    $issues = [];
    [$inventory, $inventoryHash, $modulesHash] = $this->readInventory($inventoryPath, $modulesPath, $issues);
    if (($expectedInventoryHash !== null && $expectedInventoryHash !== $inventoryHash)
      || ($expectedModulesHash !== null && $expectedModulesHash !== $modulesHash)) {
      throw new RuntimeException('Bundled release data differs from its recorded Core source hashes.');
    }
    if ($issues !== []) {
      throw new RuntimeException(implode(' ', array_column($issues, 'message')));
    }
    return $inventory;
  }

  /** A manifest policy observation is separate from whole-release readiness.
   * @return array<string, mixed>
   */
  public function validateManifestFiles(string $inventoryPath, string $modulesPath, string $manifestPath, ?string $reference = null,
    ReleasePurpose $purpose = ReleasePurpose::CurrentTrain, ?string $evidencePath = null): array
  {
    $issues = [];
    $inventoryHash = $modulesHash = $manifestHash = $name = null;
    try {
      [$inventory, $inventoryHash, $modulesHash] = $this->readInventory($inventoryPath, $modulesPath, $issues);
      [$manifest, $manifestHash] = $this->read($manifestPath);
      $owner = [];
      if ($evidencePath !== null) {
        [$owner] = $this->read($evidencePath);
        $checkout = SourceCheckout::at(dirname($manifestPath));
        if (($owner['schema'] ?? null) !== 2 || ($owner['purpose'] ?? null) !== $purpose->value
          || ($owner['package'] ?? null) !== ($manifest['name'] ?? null) || ($owner['manifestSha256'] ?? null) !== $manifestHash
          || ($owner['sourceSha'] ?? null) !== $checkout->head() || !$checkout->isClean()) {
          throw new RuntimeException('Owner support evidence must bind this purpose and the actual clean consumer source/manifest.');
        }
        $checkout->verifyFiles($owner['sourceSha'], [$manifestPath]);
      }
      $name = $manifest['name'] ?? null;
      if (!is_string($name) || !isset($inventory->packages[$name])) {
        throw new RuntimeException('The manifest must identify a canonical planned module.');
      }
      if ($purpose === ReleasePurpose::CurrentTrain && array_key_exists('version', $manifest) && !self::versionsMatch($manifest['version'], $inventory->packages[$name]['version'])) {
        $issues[] = self::issue('manifest-version', 'version', 'An explicit manifest version must match the reviewed target version.');
      }
      if ($reference !== null) {
        if (strlen($reference) > 1024 || preg_match('~\Arefs/(?:heads|tags|pull)/[A-Za-z0-9][A-Za-z0-9._/-]*\z~', $reference) !== 1) {
          throw new RuntimeException('Reference checks require the actual full Git ref.');
        }
        $version = match (true) {
          str_starts_with($reference, 'refs/tags/') => substr($reference, strlen('refs/tags/')),
          str_starts_with($reference, 'refs/heads/release/') => substr($reference, strlen('refs/heads/release/')),
          default => null,
        };
        if ($version !== null && $inventory->publication[$name] !== ModulePublication::Released) {
          $issues[] = self::issue('unreleased-publication', 'reference', 'An unreleased module must be promoted through canonical lifecycle review before a release branch or version tag can pass.');
        }
        if ($purpose === ReleasePurpose::CurrentTrain && $version !== null && !self::versionsMatch($version, $inventory->packages[$name]['version'])) {
          $issues[] = self::issue('release-reference', 'reference', 'The actual release branch or version tag must match this module’s reviewed planned version.');
        }
      }
      foreach (['require', 'require-dev'] as $section) {
        foreach (self::map($manifest[$section] ?? new stdClass()) as $dependency => $constraint) {
          if (!str_starts_with($dependency, 'assegaiphp/')) { continue; }
          $plan = $inventory->packages[$dependency] ?? null;
          $line = $inventory->packageReleaseLines[$dependency] ?? null;
          if ($plan === null || $line === null || !is_string($constraint)
            || ($inventory->publication[$name] === ModulePublication::Released && $inventory->publication[$dependency] !== ModulePublication::Released)) {
            $issues[] = self::issue('manifest-dependency', $section . '.' . $dependency, 'The dependency must identify an allowed canonical module and constraint.');
            continue;
          }
          $messages = self::rangeIssues($constraint);
          if ($purpose === ReleasePurpose::CurrentTrain && !self::versionAllowed($plan['version'], $constraint)) {
            $messages[] = 'Current-train qualification requires acceptance of the exact planned dependency artifact.';
          }
          if ($evidencePath !== null) {
            $sections = isset($owner['dependencySupport']) ? self::map($owner['dependencySupport']) : [];
            $providers = isset($sections[$section]) ? self::map($sections[$section]) : [];
            $support = $providers[$dependency] ?? null;
            $messages = [...$messages, ...DependencySupport::issues($constraint,
              $purpose === ReleasePurpose::CurrentTrain ? $plan['installConstraint'] : null, $support)];
          }
          foreach ($messages as $message) {
            $issues[] = self::issue('manifest-dependency', $section . '.' . $dependency, $message);
          }
        }
      }
    } catch (RuntimeException | JsonException $error) {
      $issues[] = self::issue('invalid-input', 'inputs', $error->getMessage());
    }
    $status = $purpose === ReleasePurpose::CurrentTrain ? 'passed' : ($evidencePath === null ? 'observed' : 'compatible');
    return ['schema' => 2, 'kind' => 'package-policy', 'purpose' => $purpose->value,
      'qualification' => $evidencePath !== null ? 'consumer-support' : ($purpose === ReleasePurpose::CurrentTrain ? 'structural' : 'observation'),
      'releaseReady' => false, 'status' => $issues === [] ? $status : 'blocked', 'package' => $name, 'reference' => $reference,
      'inventorySha256' => $inventoryHash, 'canonicalModulesSha256' => $modulesHash, 'manifestSha256' => $manifestHash, 'issues' => $issues];
  }

  /** @param list<array{code: string, path: string, message: string}> $issues
   * @return array{ReleaseInventory, string, string, array<string, mixed>}
   */
  private function readInventory(string $inventoryPath, string $modulesPath, array &$issues): array
  {
    [$data, $inventoryHash] = $this->read($inventoryPath);
    [$canonical, $modulesHash] = $this->read($modulesPath);
    $definition = $this->modules($canonical);
    $inventory = $this->inventory($data, $definition['releaseLines'], $definition['publication']);
    foreach (array_diff(array_keys($definition['modules']), array_keys($inventory->packages)) as $name) {
      $issues[] = self::issue('missing-module', 'packages.' . $name, 'Every canonical module requires a release plan.');
    }
    foreach (array_diff(array_keys($inventory->packages), array_keys($definition['modules'])) as $name) {
      $issues[] = self::issue('unknown-module', 'packages.' . $name, 'The plan contains an unknown, retired or consumer package.');
    }
    $this->constraints($inventory, $issues);
    return [$inventory, $inventoryHash, $modulesHash, $canonical];
  }

  /** @return array{array<string, mixed>, string} */
  public function read(string $filename): array
  {
    if (!is_file($filename) || !is_readable($filename)) {
      throw new RuntimeException('Release inputs must be explicit readable JSON files.');
    }
    $contents = @file_get_contents($filename, length: 1048577);
    if ($contents === false || strlen($contents) > 1048576) {
      throw new RuntimeException('A release input is unreadable or exceeds 1 MiB.');
    }
    $data = json_decode($contents, false, 64, JSON_THROW_ON_ERROR);
    if (!$data instanceof stdClass) {
      throw new RuntimeException('Release inputs must contain JSON objects.');
    }
    return [get_object_vars($data), 'sha256:' . hash('sha256', $contents)];
  }

  /** @param array<string, mixed> $data
   * @return array{modules: array<string, array<string, mixed>>, releaseLines: array<string, array<string, string>>, publication: array<string, ModulePublication>}
   */
  private function modules(array $data): array
  {
    self::keys($data, ['schema', 'releaseLines', 'modules', 'consumers', 'retired']);
    if ($data['schema'] !== 1) {
      throw new RuntimeException('Unsupported canonical module schema.');
    }
    $modules = self::map($data['modules']);
    $consumers = self::map($data['consumers']);
    $retired = self::map($data['retired']);
    if ($modules === [] || count($modules) > 128 || count($consumers) !== 1
      || array_intersect_key($modules, $consumers) || array_intersect_key($modules, $retired) || array_intersect_key($consumers, $retired)) {
      throw new RuntimeException('Canonical modules, Website consumer and retired aliases must be complete and distinct.');
    }
    $validated = [];
    $publication = [];
    foreach (['modules' => $modules, 'consumers' => $consumers] as $role => $entries) {
      foreach ($entries as $name => $value) {
        self::packageName($name);
        $entry = self::map($value);
        self::keys($entry, $role === 'modules' ? ['repository', 'directory', 'kind', 'versioning', 'publication'] : ['repository', 'directory', 'kind']);
        if (!is_string($entry['repository']) || preg_match('~\Aassegaiphp/[a-z0-9][a-z0-9._-]*\z~', $entry['repository']) !== 1
          || !is_string($entry['directory']) || preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $entry['directory']) !== 1
          || !in_array($entry['kind'], $role === 'modules' ? ['library', 'project'] : ['website'], true)) {
          throw new RuntimeException('Canonical repository identities, directory names and roles are invalid.');
        }
        if ($role === 'modules') {
          if (!is_string($entry['versioning']) || ModuleVersioning::tryFrom($entry['versioning']) === null) {
            throw new RuntimeException('Canonical modules require a recognized versioning mode.');
          }
          $state = is_string($entry['publication']) ? ModulePublication::tryFrom($entry['publication']) : null;
          if ($state === null) {
            throw new RuntimeException('Canonical modules require a recognized publication lifecycle.');
          }
          $publication[$name] = $state;
          $validated[$name] = $entry;
        }
      }
    }
    foreach ($retired as $name => $value) {
      self::packageName($name);
      $entry = self::map($value);
      self::keys($entry, ['reason']);
      if (!in_array($entry['reason'], ['obsolete', 'abandoned'], true)) {
        throw new RuntimeException('Retired packages require an explicit classification.');
      }
    }
    $mappedNames = array_keys(array_filter($validated, static fn(array $module): bool => $module['versioning'] !== ModuleVersioning::Framework->value));
    $releaseLines = [];
    foreach (self::map($data['releaseLines']) as $line => $value) {
      if (preg_match(self::LINE, $line) !== 1) {
        throw new RuntimeException('Canonical release mappings require major.minor keys.');
      }
      $mapping = self::map($value);
      if (array_diff(array_keys($mapping), $mappedNames) || array_diff($mappedNames, array_keys($mapping))) {
        throw new RuntimeException('Every canonical release mapping must cover exactly the modules with established separate version streams.');
      }
      $packageLines = [];
      foreach ($validated as $name => $module) {
        $packageLine = $module['versioning'] === ModuleVersioning::Framework->value ? $line : $mapping[$name];
        if (!is_string($packageLine) || preg_match(self::LINE, $packageLine) !== 1) {
          throw new RuntimeException('Canonical package release mappings require explicit major.minor values.');
        }
        $packageLines[$name] = $packageLine;
      }
      $releaseLines[$line] = $packageLines;
    }
    if ($releaseLines === [] || count($releaseLines) > 128) {
      throw new RuntimeException('Canonical release mappings must be nonempty and bounded.');
    }
    uksort($releaseLines, static fn(string $left, string $right): int => version_compare($left, $right));
    $previous = null;
    foreach ($releaseLines as $mapping) {
      foreach ($validated as $name => $module) {
        if ($previous !== null && $module['versioning'] === ModuleVersioning::LegacyCoordinated->value
          && version_compare($mapping[$name], $previous[$name], '<=')) {
          throw new RuntimeException('Legacy coordinated module lines must advance at each framework release milestone.');
        }
      }
      $previous = $mapping;
    }
    return ['modules' => $validated, 'releaseLines' => $releaseLines, 'publication' => $publication];
  }

  /** @param array<string, mixed> $data
   * @param array<string, array<string, string>> $releaseLines
   * @param array<string, ModulePublication> $publication
   */
  private function inventory(array $data, array $releaseLines, array $publication): ReleaseInventory
  {
    self::keys($data, ['schema', 'releaseLine', 'packages', 'website']);
    if ($data['schema'] !== 1 || !is_string($data['releaseLine']) || preg_match(self::LINE, $data['releaseLine']) !== 1) {
      throw new RuntimeException('A release inventory requires schema 1 and an agreed canonical major.minor line.');
    }
    if (!isset($releaseLines[$data['releaseLine']])) {
      throw new RuntimeException('The target framework line has no reviewed canonical module mapping.');
    }
    $packages = [];
    foreach (self::map($data['packages']) as $name => $value) {
      self::packageName($name);
      $entry = self::map($value);
      self::keys($entry, ['version', 'installConstraint']);
      if (!self::text($entry['version'], 128) || !self::text($entry['installConstraint'], 1024)) {
        throw new RuntimeException('Each planned package requires an exact version and install constraint.');
      }
      $packages[$name] = ['version' => $entry['version'], 'installConstraint' => $entry['installConstraint']];
    }
    if ($packages === [] || count($packages) > 128) {
      throw new RuntimeException('The release package plan must be nonempty and bounded.');
    }
    $website = self::map($data['website']);
    self::keys($website, ['documentationVersion', 'previousDocumentationVersion', 'articlePath', 'upgradeFrom', 'upgradeTo', 'archives']);
    foreach (['documentationVersion', 'previousDocumentationVersion', 'articlePath', 'upgradeFrom', 'upgradeTo'] as $key) {
      if (!self::text($website[$key], 1024)) {
        throw new RuntimeException('Website requirements must be explicit bounded strings.');
      }
    }
    if ($website['documentationVersion'] !== $data['releaseLine']
      || preg_match(self::LINE, $website['previousDocumentationVersion']) !== 1
      || $website['previousDocumentationVersion'] === $website['documentationVersion']) {
      throw new RuntimeException('Website documentation must match the new line and explicitly preserve its previous version.');
    }
    $archives = [];
    foreach (self::map($website['archives']) as $version => $hash) {
      if (preg_match(self::LINE, $version) !== 1 || $version === $website['documentationVersion'] || !self::isHash($hash)) {
        throw new RuntimeException('Archived guide baselines require version keys and exact SHA-256 digests.');
      }
      $archives[$version] = $hash;
    }
    if (!isset($archives[$website['previousDocumentationVersion']])) {
      throw new RuntimeException('The previous documentation archive baseline is required.');
    }
    ksort($packages, SORT_STRING);
    ksort($archives, SORT_STRING);
    return new ReleaseInventory($data['releaseLine'], $packages, new WebsiteRequirements(
      $website['documentationVersion'], $website['previousDocumentationVersion'], $website['articlePath'],
      $website['upgradeFrom'], $website['upgradeTo'], $archives,
    ), $releaseLines[$data['releaseLine']], $publication);
  }

  /** @param list<array{code: string, path: string, message: string}> $issues */
  private function constraints(ReleaseInventory $inventory, array &$issues): void
  {
    if (!class_exists(VersionParser::class) || !class_exists(Intervals::class)) {
      $issues[] = self::issue('constraint-support-unavailable', 'packages', 'Composer semver is required to verify version stability and range containment.');
      return;
    }
    foreach ($inventory->packages as $name => $package) {
      $line = $inventory->packageReleaseLines[$name] ?? null;
      if ($line === null) { continue; }
      foreach (self::constraintIssues($package['version'], $package['installConstraint'], $line) as $message) {
        $issues[] = self::issue('release-line', 'packages.' . $name, $message);
      }
    }
  }

  /** Uses Composer's parser for both exact labels (including two-part tags) and complete constraint sets.
   * @return list<string>
   */
  public static function constraintIssues(string $version, string $constraint, string $releaseLine): array
  {
    if (preg_match(self::LINE, $releaseLine) !== 1) {
      return ['The release line is not a canonical major.minor value.'];
    }
    if (!class_exists(VersionParser::class) || !class_exists(Intervals::class)) {
      return ['Composer semver support is unavailable.'];
    }
    try {
      $parser = new VersionParser();
      $normalized = $parser->normalize($version);
      $parts = explode('.', $releaseLine);
      if (VersionParser::parseStability($version) !== 'stable' || !str_starts_with($normalized, $releaseLine . '.')) {
        return ['The planned stable version must share the agreed major.minor line.'];
      }
      $range = self::numericRange($constraint);
      if (!$range->matches(new Constraint('==', $normalized))) {
        return ['The constraint does not accept the planned version.'];
      }
      $upper = $parts[0] . '.' . ((int) $parts[1] + 1) . '.0.0-dev';
      $outside = $parser->parseConstraints('<' . $releaseLine . '.0.0-dev || >=' . $upper);
      if (Intervals::haveIntersections($range, $outside)) {
        return ['The compatibility envelope permits another canonical release line.'];
      }
      return [];
    } catch (\UnexpectedValueException | \InvalidArgumentException $error) {
      return ['Composer could not validate the version or constraint: ' . $error->getMessage()];
    }
  }

  /** @return list<string> */
  public static function subsetIssues(string $constraint, string $envelope): array
  {
    if (!class_exists(VersionParser::class) || !class_exists(Intervals::class)) {
      return ['Composer semver support is unavailable.'];
    }
    try {
      return Intervals::isSubsetOf(self::numericRange($constraint), self::numericRange($envelope))
        ? [] : ['The requirement permits versions outside the reviewed compatibility envelope.'];
    } catch (\UnexpectedValueException | \InvalidArgumentException $error) {
      return ['Composer could not validate the requirement or envelope: ' . $error->getMessage()];
    }
  }

  public static function versionAllowed(mixed $version, string $constraint): bool
  {
    if (!is_string($version) || !class_exists(VersionParser::class) || !class_exists(Intervals::class)) { return false; }
    try {
      return VersionParser::parseStability($version) === 'stable'
        && self::numericRange($constraint)->matches(new Constraint('==', (new VersionParser())->normalize($version)));
    } catch (\UnexpectedValueException | \InvalidArgumentException) {
      return false;
    }
  }

  /** @return list<string> */
  public static function rangeIssues(string $constraint): array
  {
    try { self::numericRange($constraint); return []; }
    catch (\InvalidArgumentException | \UnexpectedValueException $error) { return [$error->getMessage()]; }
  }

  private static function numericRange(string $constraint): ConstraintInterface
  {
    return ComposerManifest::parseRequirement($constraint);
  }

  public static function versionsMatch(mixed $left, mixed $right): bool
  {
    if (!is_string($left) || !is_string($right) || !class_exists(VersionParser::class)) {
      return false;
    }
    try {
      $parser = new VersionParser();
      return VersionParser::parseStability($left) === 'stable' && VersionParser::parseStability($right) === 'stable'
        && $parser->normalize($left) === $parser->normalize($right);
    } catch (\UnexpectedValueException | \InvalidArgumentException) {
      return false;
    }
  }

  /** @return array<string, mixed> */
  public static function map(mixed $value): array
  {
    if (!$value instanceof stdClass) {
      throw new RuntimeException('Release maps must be JSON objects.');
    }
    $result = [];
    foreach (get_object_vars($value) as $key => $entry) {
      if (!is_string($key) || ctype_digit($key)) {
        throw new RuntimeException('Release maps require named object keys.');
      }
      $result[$key] = $entry;
    }
    return $result;
  }

  /** @param array<string, mixed> $data
   * @param list<string> $keys
   */
  public static function keys(array $data, array $keys): void
  {
    if (count($data) !== count($keys) || array_diff(array_keys($data), $keys)) {
      throw new RuntimeException('A release object has missing or unknown fields.');
    }
  }

  public static function isHash(mixed $value): bool
  {
    return is_string($value) && preg_match('/\Asha256:[a-f0-9]{64}\z/', $value) === 1;
  }

  private static function text(mixed $value, int $limit): bool
  {
    return is_string($value) && $value !== '' && strlen($value) <= $limit && !preg_match('/[\x00-\x1f\x7f]/', $value);
  }

  private static function packageName(string $name): void
  {
    if (strlen($name) > 128 || preg_match('~\Aassegaiphp/[a-z0-9][a-z0-9._-]*\z~', $name) !== 1) {
      throw new RuntimeException('Canonical package keys must identify first-party Composer packages.');
    }
  }

  /** @return array{code: string, path: string, message: string} */
  public static function issue(string $code, string $path, string $message): array
  {
    return ['code' => $code, 'path' => $path, 'message' => $message];
  }
}
