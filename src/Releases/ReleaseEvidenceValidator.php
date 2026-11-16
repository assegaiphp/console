<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

/** Validates generated owner receipts; it never turns a plan into publication authority. */
final class ReleaseEvidenceValidator
{
  /** @param array<string, mixed> $canonical
   * @param array<string, mixed> $evidence
   * @param list<array{code: string, path: string, message: string}> $issues
   */
  public function validate(ReleaseInventory $inventory, ReleaseStage $stage, string $inventoryHash, string $modulesHash, array $canonical, array $evidence, array &$issues): void
  {
    if (($evidence['schema'] ?? null) !== 1 || ($evidence['inventorySha256'] ?? null) !== $inventoryHash
      || ($evidence['canonicalModulesSha256'] ?? null) !== $modulesHash) {
      $issues[] = ReleaseInventoryValidator::issue('stale-evidence', 'evidence', 'Evidence must bind the exact inventory and canonical module bytes.');
      return;
    }
    $membership = ReleaseInventoryValidator::map($evidence['membership'] ?? null);
    $publishedNames = $membership['publishedPackages'] ?? null;
    $unpublishedNames = $membership['unpublishedModules'] ?? null;
    $retired = ReleaseInventoryValidator::map($canonical['retired']);
    $released = $inventory->releasedPackages();
    if (($membership['status'] ?? null) !== 'passed' || ($membership['canonicalModulesSha256'] ?? null) !== $modulesHash
      || !ReleaseInventoryValidator::isHash($membership['catalogSha256'] ?? null)
      || !is_array($publishedNames) || !array_is_list($publishedNames)
      || !is_array($unpublishedNames) || !array_is_list($unpublishedNames)
      || array_filter([...$publishedNames, ...$unpublishedNames], static fn(mixed $name): bool => !is_string($name)) !== []) {
      $issues[] = ReleaseInventoryValidator::issue('membership-evidence', 'evidence.membership', 'A catalog reconciliation bound to canonical membership is required.');
    } else {
      $plannedNames = array_keys($inventory->packages);
      if (array_diff($publishedNames, [...array_keys($released), ...array_keys($retired)])
        || array_diff($unpublishedNames, $plannedNames)
        || array_diff($plannedNames, [...$publishedNames, ...$unpublishedNames])
        || array_intersect($publishedNames, $unpublishedNames)) {
        $issues[] = ReleaseInventoryValidator::issue('membership-evidence', 'evidence.membership', 'Published, unpublished and retired packages must all be explicitly accounted for.');
      }
    }
    $modules = ReleaseInventoryValidator::map($evidence['modules'] ?? null);
    if (array_diff(array_keys($inventory->packages), array_keys($modules)) || array_diff(array_keys($modules), array_keys($inventory->packages))) {
      $issues[] = ReleaseInventoryValidator::issue('module-evidence', 'evidence.modules', 'Source/check evidence must cover exactly the planned modules.');
    }
    $sources = [];
    $dependencies = [];
    foreach ($inventory->packages as $name => $plan) {
      $module = ReleaseInventoryValidator::map($modules[$name] ?? null);
      $source = $module['sourceSha'] ?? null;
      $dependency = $module['dependencySha256'] ?? null;
      if (!self::gitSha($source) || !ReleaseInventoryValidator::isHash($dependency)) {
        $issues[] = ReleaseInventoryValidator::issue('source-identity', 'evidence.modules.' . $name, 'An exact candidate source and dependency identity are required.');
        continue;
      }
      $isReleased = isset($released[$name]);
      if ($isReleased) {
        $sources[$name] = $source;
        $dependencies[$name] = $dependency;
      }
      $checks = ReleaseInventoryValidator::map($module['checks'] ?? null);
      foreach ($isReleased ? ['package', 'releaseNotes'] : [] as $kind) {
        $check = ReleaseInventoryValidator::map($checks[$kind] ?? null);
        if (($check['status'] ?? null) !== 'passed' || ($check['sourceSha'] ?? null) !== $source
          || ($check['dependencySha256'] ?? null) !== $dependency) {
          $issues[] = ReleaseInventoryValidator::issue('candidate-check', 'evidence.modules.' . $name . '.checks.' . $kind,
            'A completed check must name the exact candidate source and dependencies.');
        }
      }
      foreach (['require', 'require-dev'] as $section) {
        foreach (ReleaseInventoryValidator::map($module[$section] ?? null) as $dependencyName => $constraint) {
          if (!str_starts_with($dependencyName, 'assegaiphp/')) {
            continue;
          }
          $dependencyPlan = $inventory->packages[$dependencyName] ?? null;
          $dependencyLine = $inventory->packageReleaseLines[$dependencyName] ?? null;
          if ($dependencyPlan === null || $dependencyLine === null || !is_string($constraint)) {
            $issues[] = ReleaseInventoryValidator::issue('dependency-membership', 'evidence.modules.' . $name . '.' . $section,
              'First-party dependencies must identify a planned canonical module and a constraint.');
            continue;
          }
          if ($isReleased && !isset($released[$dependencyName])) {
            $issues[] = ReleaseInventoryValidator::issue('unreleased-dependency', 'evidence.modules.' . $name . '.' . $section . '.' . $dependencyName,
              'A released module cannot depend on an unreleased module.');
          }
          foreach (ReleaseInventoryValidator::subsetIssues($constraint, $dependencyPlan['installConstraint']) as $message) {
            $issues[] = ReleaseInventoryValidator::issue('dependency-line', 'evidence.modules.' . $name . '.' . $section . '.' . $dependencyName, $message);
          }
        }
      }
    }
    ksort($sources, SORT_STRING);
    ksort($dependencies, SORT_STRING);
    $sourcesHash = 'sha256:' . hash('sha256', json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $dependenciesHash = 'sha256:' . hash('sha256', json_encode($dependencies, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $composer = ReleaseInventoryValidator::map($evidence['composer'] ?? null);
    if (($composer['status'] ?? null) !== 'passed' || ($composer['candidateSourcesSha256'] ?? null) !== $sourcesHash
      || ($composer['candidateDependenciesSha256'] ?? null) !== $dependenciesHash
      || !ReleaseInventoryValidator::isHash($composer['lockSha256'] ?? null)) {
      $issues[] = ReleaseInventoryValidator::issue('composer-evidence', 'evidence.composer', 'Composer resolution must bind every exact candidate and its resolved lock.');
    }
    $resolved = ReleaseInventoryValidator::map($composer['versions'] ?? null);
    if (array_diff(array_keys($resolved), array_keys($released)) || array_diff(array_keys($released), array_keys($resolved))) {
      $issues[] = ReleaseInventoryValidator::issue('composer-versions', 'evidence.composer.versions', 'Resolved packages must match the entire reviewed plan.');
    }
    foreach ($released as $name => $plan) {
      if (!ReleaseInventoryValidator::versionsMatch($resolved[$name] ?? null, $plan['version'])) {
        $issues[] = ReleaseInventoryValidator::issue('composer-versions', 'evidence.composer.versions.' . $name, 'The resolved stable version must match the reviewed version after Composer normalization.');
      }
    }
    $this->cliPlans($inventory, $sources[ReleaseInventory::CONSOLE_PACKAGE] ?? null, $dependencies[ReleaseInventory::CONSOLE_PACKAGE] ?? null, $evidence['cli'] ?? null, $issues);
    $this->website($inventoryHash, $modulesHash, $evidence['website'] ?? null, $issues);
    if ($stage === ReleaseStage::Publish) {
      $this->publication($inventory, $sources, $evidence['publication'] ?? null, $issues);
    }
  }

  /** @param list<array{code: string, path: string, message: string}> $issues */
  private function cliPlans(ReleaseInventory $inventory, ?string $source, ?string $dependency, mixed $value, array &$issues): void
  {
    $cli = ReleaseInventoryValidator::map($value);
    if ($source === null || $dependency === null || ($cli['sourceSha'] ?? null) !== $source || ($cli['dependencySha256'] ?? null) !== $dependency) {
      $issues[] = ReleaseInventoryValidator::issue('cli-source', 'evidence.cli', 'CLI catalog/default/update observations must name the reviewed Console source and dependencies.');
    }
    foreach (['catalog', 'defaults', 'update'] as $kind) {
      $recommendations = ReleaseInventoryValidator::map($cli[$kind] ?? null);
      if ($kind === 'catalog' && array_diff(array_keys($inventory->releasedPackages()), array_keys($recommendations))) {
        $issues[] = ReleaseInventoryValidator::issue('cli-membership', 'evidence.cli.catalog', 'CLI recommendations must include every canonical module.');
      }
      if ($recommendations === []) {
        $issues[] = ReleaseInventoryValidator::issue('cli-plan', 'evidence.cli.' . $kind, 'A real catalog/default/update observation is required.');
      }
      foreach ($recommendations as $name => $constraint) {
        $plan = $inventory->packages[$name] ?? null;
        $line = $inventory->packageReleaseLines[$name] ?? null;
        if ($plan === null || $line === null || ($inventory->publication[$name] ?? null) !== ModulePublication::Released || !is_string($constraint)) {
          $issues[] = ReleaseInventoryValidator::issue('cli-plan', 'evidence.cli.' . $kind, 'CLI plans contain an unplanned package or invalid constraint.');
          continue;
        }
        if ($kind === 'catalog' && $constraint !== $plan['installConstraint']) {
          $issues[] = ReleaseInventoryValidator::issue('cli-recommendation', 'evidence.cli.catalog.' . $name, 'The catalog recommendation differs from the reviewed inventory.');
        }
        foreach (ReleaseInventoryValidator::subsetIssues($constraint, $plan['installConstraint']) as $message) {
          $issues[] = ReleaseInventoryValidator::issue('cli-line', 'evidence.cli.' . $kind . '.' . $name, $message);
        }
      }
    }
  }

  /** @param list<array{code: string, path: string, message: string}> $issues */
  private function website(string $inventoryHash, string $modulesHash, mixed $value, array &$issues): void
  {
    $website = ReleaseInventoryValidator::map($value);
    $receipt = ReleaseInventoryValidator::map($website['content'] ?? null);
    $checks = ReleaseInventoryValidator::map($receipt['checks'] ?? null);
    $content = ReleaseInventoryValidator::map($checks['content'] ?? null);
    $source = $website['sourceSha'] ?? null;
    $lock = $website['composerLockSha256'] ?? null;
    if (!self::gitSha($source) || !ReleaseInventoryValidator::isHash($lock)
      || ($receipt['schema'] ?? null) !== 1 || ($receipt['kind'] ?? null) !== 'website-content'
      || ($receipt['inventorySha256'] ?? null) !== $inventoryHash || ($receipt['canonicalModulesSha256'] ?? null) !== $modulesHash
      || ($receipt['sourceSha'] ?? null) !== $source || ($receipt['sourceClean'] ?? null) !== true
      || ($receipt['composerLockSha256'] ?? null) !== $lock || ($content['status'] ?? null) !== 'passed'
      || ($content['errors'] ?? null) !== []
      || (array_key_exists('passed', $receipt) && $receipt['passed'] !== true)
      || (array_key_exists('errors', $receipt) && $receipt['errors'] !== [])) {
      $issues[] = ReleaseInventoryValidator::issue('website-content', 'evidence.website.content', 'Website content must pass against the reviewed clean candidate and exact lock/input digests.');
    }
    foreach (['runtime', 'render'] as $kind) {
      $check = ReleaseInventoryValidator::map($website[$kind] ?? null);
      if (($check['status'] ?? null) !== 'passed' || ($check['sourceSha'] ?? null) !== $source || ($check['composerLockSha256'] ?? null) !== $lock) {
        $issues[] = ReleaseInventoryValidator::issue('website-check', 'evidence.website.' . $kind, 'Website runtime/render evidence must bind the same reviewed candidate and lock.');
      }
    }
  }

  /** @param array<string, string> $sources
   * @param list<array{code: string, path: string, message: string}> $issues
   */
  private function publication(ReleaseInventory $inventory, array $sources, mixed $value, array &$issues): void
  {
    $published = ReleaseInventoryValidator::map($value);
    foreach ($inventory->releasedPackages() as $name => $plan) {
      $entry = isset($published[$name]) ? ReleaseInventoryValidator::map($published[$name]) : [];
      if (($entry['status'] ?? null) !== 'observed' || !ReleaseInventoryValidator::versionsMatch($entry['version'] ?? null, $plan['version'])
        || ($entry['stability'] ?? null) !== 'stable' || ($entry['sourceSha'] ?? null) !== ($sources[$name] ?? null)
        || ($entry['tagSourceSha'] ?? null) !== ($sources[$name] ?? null)
        || !is_int($entry['observedAt'] ?? null) || $entry['observedAt'] > time() || $entry['observedAt'] < time() - 86400) {
        $issues[] = ReleaseInventoryValidator::issue('package-publication', 'evidence.publication.' . $name,
          'A fresh successful stable-package observation must match the planned version and reviewed tag/source.');
      }
    }
  }

  private static function gitSha(mixed $value): bool
  {
    return is_string($value) && preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/', $value) === 1;
  }
}
