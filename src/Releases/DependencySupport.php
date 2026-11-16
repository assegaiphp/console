<?php

declare(strict_types=1);

namespace Assegai\Console\Releases;

use Assegai\Console\Util\ComposerManifest;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;

/** Consumer-owned support contracts and executed samples are separate evidence. */
final class DependencySupport
{
  /** The containing owner receipt binds consumer source, manifest and require section/key.
   * @return list<string>
   */
  public static function issues(string $requirement, ?string $recommendation, mixed $value): array
  {
    try {
      $claimed = ComposerManifest::parseRequirement($requirement);
      $covered = $recommendation === null ? [] : [ComposerManifest::parseRequirement($recommendation)];
      if ($value === null && $covered !== [] && Intervals::isSubsetOf($claimed, $covered[0])) { return []; }
      $support = ReleaseInventoryValidator::map($value);
      ReleaseInventoryValidator::keys($support, ['requirement', 'contracts', 'samples']);
      if ($support['requirement'] !== $requirement || !is_array($support['contracts']) || !array_is_list($support['contracts'])
        || $support['contracts'] === [] || count($support['contracts']) > 32
        || !is_array($support['samples']) || !array_is_list($support['samples']) || count($support['samples']) > 128) {
        throw new \RuntimeException('Support must bind the literal consumer requirement and bounded reviewed contracts and executed samples.');
      }
      $samples = [];
      foreach ($support['samples'] as $entry) {
        $sample = ReleaseInventoryValidator::map($entry);
        ReleaseInventoryValidator::keys($sample, ['version', 'sourceSha', 'lockSha256', 'graphSha256', 'check']);
        $check = ReleaseInventoryValidator::map($sample['check']);
        ReleaseInventoryValidator::keys($check, ['status', 'environment']);
        if (!is_string($sample['version']) || !ReleaseInventoryValidator::versionAllowed($sample['version'], $requirement)
          || !ReleaseEvidenceValidator::gitSha($sample['sourceSha']) || !ReleaseInventoryValidator::isHash($sample['lockSha256'])
          || !ReleaseInventoryValidator::isHash($sample['graphSha256']) || $check['status'] !== 'passed'
          || !is_string($check['environment']) || $check['environment'] === '' || strlen($check['environment']) > 256
          || preg_match('/[\x00-\x1f\x7f]/', $check['environment'])) {
          throw new \RuntimeException('Executed support samples require stable provider/source, actual lock/graph identities and completed behavior checks/environment.');
        }
        $samples[] = $sample['version'];
      }
      foreach ($support['contracts'] as $entry) {
        $contract = ReleaseInventoryValidator::map($entry);
        ReleaseInventoryValidator::keys($contract, ['interval', 'historySha256', 'apiBasisSha256']);
        if (!is_string($contract['interval']) || !ReleaseInventoryValidator::isHash($contract['historySha256'])
          || !ReleaseInventoryValidator::isHash($contract['apiBasisSha256'])) {
          throw new \RuntimeException('A reviewed consumer support interval requires provider release-history and API-basis identities.');
        }
        $interval = ComposerManifest::parseRequirement($contract['interval']);
        if (!Intervals::haveIntersections($interval, $claimed)
          || !array_any($samples, static fn(string $version): bool => ReleaseInventoryValidator::versionAllowed($version, $contract['interval']))) {
          throw new \RuntimeException('Each reviewed interval needs an executed sample in the consumer requirement; samples do not establish the interval themselves.');
        }
        $covered[] = $interval;
      }
      if (!Intervals::isSubsetOf($claimed, new MultiConstraint($covered, false))) {
        throw new \RuntimeException('The complete claimed requirement is not covered by reviewed consumer support intervals.');
      }
      return [];
    } catch (\RuntimeException | \InvalidArgumentException | \UnexpectedValueException $error) {
      return [$error->getMessage()];
    }
  }
}
