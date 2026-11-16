<?php

namespace Tests\Unit;

use Assegai\Console\Releases\ReleaseInventoryValidator;
use Assegai\Console\Releases\ReleaseStage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use ReflectionClass;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

// Controlled protocol fixtures, never a proposed framework release target.
final class ReleaseInventoryValidatorTest extends TestCase
{
  private string $releaseDirectory;
  /** @var array<string, string> */
  private array $releaseFiles;
  /** @var array<string, mixed> */
  private array $canonical;
  /** @var array<string, mixed> */
  private array $releasePlan;
  /** @var array<string, mixed> */
  private array $releaseEvidence;
  private string $inventoryFile;
  private string $modulesFile;
  private string $evidenceFile;

  protected function setUp(): void
  {
    $this->releaseDirectory = sys_get_temp_dir() . '/assegai-release-check-' . bin2hex(random_bytes(8));
    mkdir($this->releaseDirectory);
    $this->releaseFiles = [];
    $this->canonical = [
      'schema' => 1,
      'releaseLines' => ['1.1' => ['assegaiphp/attributes' => '1.0', 'assegaiphp/rabbitmq' => '3.3'],
        '1.2' => ['assegaiphp/attributes' => '1.0', 'assegaiphp/rabbitmq' => '3.4']],
      'modules' => [
        'assegaiphp/core' => ['repository' => 'assegaiphp/core', 'directory' => 'core', 'kind' => 'library', 'versioning' => 'framework', 'publication' => 'released'],
        'assegaiphp/console' => ['repository' => 'assegaiphp/console', 'directory' => 'console', 'kind' => 'library', 'versioning' => 'framework', 'publication' => 'released'],
        'assegaiphp/attributes' => ['repository' => 'assegaiphp/attributes', 'directory' => 'attributes', 'kind' => 'library', 'versioning' => 'stable-dependency', 'publication' => 'released'],
        'assegaiphp/rabbitmq' => ['repository' => 'assegaiphp/rabbitmq', 'directory' => 'rabbitmq', 'kind' => 'library', 'versioning' => 'legacy-coordinated', 'publication' => 'released'],
      ],
      'consumers' => ['assegaiphp/website' => ['repository' => 'assegaiphp/website-v3', 'directory' => 'website-v3', 'kind' => 'website']],
      'retired' => ['assegaiphp/cli' => ['reason' => 'abandoned']],
    ];
    $this->releasePlan = [
      'schema' => 1, 'releaseLine' => '1.2',
      'packages' => [
        'assegaiphp/core' => ['version' => '1.2.0', 'installConstraint' => '~1.2.0'],
        'assegaiphp/console' => ['version' => '1.2.1', 'installConstraint' => '~1.2.1'],
        'assegaiphp/attributes' => ['version' => '1.0.3', 'installConstraint' => '~1.0.3'],
        'assegaiphp/rabbitmq' => ['version' => '3.4.1', 'installConstraint' => '~3.4.1'],
      ],
      'website' => [
        'documentationVersion' => '1.2', 'previousDocumentationVersion' => '0.10', 'articlePath' => 'releases/test-release',
        'upgradeFrom' => '0.10.0', 'upgradeTo' => '1.2.0',
        'archives' => ['0.9' => 'sha256:' . str_repeat('9', 64), '0.10' => 'sha256:' . str_repeat('a', 64)],
      ],
    ];
    $this->inventoryFile = $this->writeRelease('inventory', $this->releasePlan);
    $this->modulesFile = $this->writeRelease('modules', $this->canonical);
    $inventoryHash = 'sha256:' . self::hashFile($this->inventoryFile);
    $modulesHash = 'sha256:' . self::hashFile($this->modulesFile);
    $this->releaseEvidence = ['schema' => 1, 'inventorySha256' => $inventoryHash, 'canonicalModulesSha256' => $modulesHash,
      'membership' => ['status' => 'passed', 'canonicalModulesSha256' => $modulesHash, 'catalogSha256' => 'sha256:' . str_repeat('f', 64),
        'publishedPackages' => array_keys($this->releasePlan['packages']), 'unpublishedModules' => []],
      'modules' => [], 'cli' => ['catalog' => [], 'defaults' => [], 'update' => []], 'publication' => [],
    ];
    $sources = $dependencies = $versions = [];
    foreach ($this->releasePlan['packages'] as $name => $package) {
      $source = sha1($name);
      $dependency = 'sha256:' . hash('sha256', $name);
      $sources[$name] = $source;
      $dependencies[$name] = $dependency;
      $versions[$name] = $package['version'];
      $check = ['status' => 'passed', 'sourceSha' => $source, 'dependencySha256' => $dependency];
      $this->releaseEvidence['modules'][$name] = ['sourceSha' => $source, 'dependencySha256' => $dependency,
        'require' => new stdClass(), 'require-dev' => new stdClass(), 'checks' => ['package' => $check, 'releaseNotes' => $check]];
      foreach (['catalog', 'defaults', 'update'] as $kind) {
        $this->releaseEvidence['cli'][$kind][$name] = $package['installConstraint'];
      }
      $this->releaseEvidence['publication'][$name] = ['status' => 'observed', 'version' => $package['version'],
        'stability' => 'stable', 'sourceSha' => $source, 'tagSourceSha' => $source, 'observedAt' => time()];
    }
    ksort($sources, SORT_STRING);
    ksort($dependencies, SORT_STRING);
    $this->releaseEvidence['cli']['sourceSha'] = $sources[PACKAGE_NAME_CLI];
    $this->releaseEvidence['cli']['dependencySha256'] = $dependencies[PACKAGE_NAME_CLI];
    $this->releaseEvidence['composer'] = ['status' => 'passed', 'candidateSourcesSha256' => 'sha256:' . hash('sha256', json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
      'candidateDependenciesSha256' => 'sha256:' . hash('sha256', json_encode($dependencies, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
      'lockSha256' => 'sha256:' . str_repeat('b', 64), 'versions' => $versions];
    $websiteSource = str_repeat('c', 40);
    $websiteLock = 'sha256:' . str_repeat('d', 64);
    $this->releaseEvidence['website'] = ['sourceSha' => $websiteSource, 'composerLockSha256' => $websiteLock,
      'content' => ['schema' => 1, 'kind' => 'website-content', 'inventorySha256' => $inventoryHash,
        'canonicalModulesSha256' => $modulesHash, 'sourceSha' => $websiteSource, 'sourceClean' => true,
        'composerLockSha256' => $websiteLock, 'checks' => ['content' => ['status' => 'passed', 'errors' => []]]],
      'runtime' => ['status' => 'passed', 'sourceSha' => $websiteSource, 'composerLockSha256' => $websiteLock],
      'render' => ['status' => 'passed', 'sourceSha' => $websiteSource, 'composerLockSha256' => $websiteLock],
    ];
    $this->evidenceFile = $this->writeRelease('evidence', $this->releaseEvidence);
  }

  protected function tearDown(): void
  {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->releaseDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      if ($file->isDir() && !$file->isLink()) { rmdir($file->getPathname()); }
      else { unlink($file->getPathname()); }
    }
    rmdir($this->releaseDirectory);
  }

  public function testAcceptsPatchDivergenceWithinOneLineAndProducesTheSharedWebsiteProjection(): void
  {
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile);
    expect($result->issues)->toBe([])->and($result->isReady)->toBeTrue();
    $inventory = $result->inventory ?? throw new RuntimeException('The valid fixture has no inventory.');
    $request = $inventory->websiteRequest($result->inventorySha256 ?? throw new RuntimeException('Missing inventory hash.'), $result->canonicalModulesSha256 ?? throw new RuntimeException('Missing module hash.'));
    expect($request['requirements']['archives'])->toEqual($this->releasePlan['website']['archives'])
      ->and($request['canonicalModulesSha256'])->toBe('sha256:' . self::hashFile($this->modulesFile))
      ->and($inventory->packageReleaseLines)->toMatchArray(['assegaiphp/core' => '1.2', 'assegaiphp/attributes' => '1.0', 'assegaiphp/rabbitmq' => '3.4']);
  }

  public function testRequiresEveryCanonicalModuleAndRejectsUnknownOrRetiredPackages(): void
  {
    unset($this->releasePlan['packages']['assegaiphp/attributes']);
    $this->releasePlan['packages']['assegaiphp/cli'] = ['version' => '1.2.0', 'installConstraint' => '~1.2.0'];
    $this->writeRelease('inventory', $this->releasePlan);
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile);
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('missing-module', 'unknown-module');
  }

  #[DataProvider('casesForRejectsMajorMinorDriftAndConstraintsPermittingAnotherMinor')]
  public function testRejectsMajorMinorDriftAndConstraintsPermittingAnotherMinor(string $version, string $constraint): void
  {
    $this->releasePlan['packages']['assegaiphp/attributes'] = ['version' => $version, 'installConstraint' => $constraint];
    $this->writeRelease('inventory', $this->releasePlan);
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile);
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('release-line');
  }

  public function testUsesComposerNormalizationForTwoPartTagsAndNarrowCustomRanges(): void
  {
    expect(ReleaseInventoryValidator::constraintIssues('10.1', '~10.1.0', '10.1'))->toBe([])
      ->and(ReleaseInventoryValidator::versionsMatch('v10.1', '10.1.0'))->toBeTrue()
      ->and(ReleaseInventoryValidator::constraintIssues('1.2.3', '>=1.2.3 <1.3.0', '1.2'))->toBe([]);
  }

  public function testSeparatesPrepareReadinessFromActualStablePublication(): void
  {
    unset($this->releaseEvidence['publication']);
    $this->writeRelease('evidence', $this->releaseEvidence);
    $validator = new ReleaseInventoryValidator();
    expect($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->isReady)->toBeTrue()
      ->and($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Publish, $this->evidenceFile)->isReady)->toBeFalse();
  }

  public function testRejectsStaleEvidenceChangedCheckSourcesAndWrongPublicationSources(): void
  {
    $this->releaseEvidence['modules']['assegaiphp/core']['checks']['package']['sourceSha'] = str_repeat('e', 40);
    $this->releaseEvidence['publication']['assegaiphp/core']['tagSourceSha'] = str_repeat('e', 40);
    $this->writeRelease('evidence', $this->releaseEvidence);
    $validator = new ReleaseInventoryValidator();
    $result = $validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Publish, $this->evidenceFile);
    expect(array_column($result->issues, 'code'))->toContain('candidate-check', 'package-publication');
    $this->releaseEvidence['inventorySha256'] = 'sha256:' . str_repeat('0', 64);
    $this->writeRelease('evidence', $this->releaseEvidence);
    expect(array_column($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->issues, 'code'))
      ->toContain('stale-evidence');
  }

  public function testRejectsMissingModuleClassificationMixedDependencyRangesAndStaleWebsiteIdentity(): void
  {
    $this->releaseEvidence['membership']['publishedPackages'][] = 'assegaiphp/unknown';
    $this->releaseEvidence['modules']['assegaiphp/console']['require'] = (object) ['assegaiphp/core' => '^1.2.0'];
    $this->releaseEvidence['website']['content']['sourceClean'] = false;
    $this->writeRelease('evidence', $this->releaseEvidence);
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile);
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('membership-evidence', 'dependency-line', 'website-content');
  }

  public function testReturnsBlockedResultsForAbsentTargetsAndMalformedMapKeysWithoutWritingInputs(): void
  {
    $before = self::hashFile($this->modulesFile);
    expect((new ReleaseInventoryValidator())->validateFiles($this->releaseDirectory . '/missing.json', $this->modulesFile)->isReady)->toBeFalse();
    $this->releasePlan['packages'] = ['123' => ['version' => '1.2.0', 'installConstraint' => '~1.2.0']];
    $this->writeRelease('inventory', $this->releasePlan);
    expect((new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile)->isReady)->toBeFalse()
      ->and(self::hashFile($this->modulesFile))->toBe($before);
  }

  #[DataProvider('casesForRejectsContradictoryWebsiteReceipts')]
  public function testRejectsContradictoryWebsiteReceipts(string $field, mixed $value): void
  {
    $this->releaseEvidence['website']['content'][$field] = $value;
    $this->writeRelease('evidence', $this->releaseEvidence);
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile);
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('website-content');
  }

  public function testRejectsStaleCLISourceAndComposerDependencyObservations(): void
  {
    $this->releaseEvidence['cli']['sourceSha'] = str_repeat('e', 40);
    $this->releaseEvidence['composer']['candidateDependenciesSha256'] = 'sha256:' . str_repeat('0', 64);
    $this->writeRelease('evidence', $this->releaseEvidence);
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile);
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('cli-source', 'composer-evidence');
  }

  public function testRunsThroughTheActualCLIWithoutLoadingWorkspaceCodeOrChangingInputs(): void
  {
    mkdir($this->releaseDirectory . '/vendor');
    $tripwire = $this->releaseDirectory . '/workspace-loaded';
    file_put_contents($this->releaseDirectory . '/vendor/autoload.php', '<?php file_put_contents(' . var_export($tripwire, true) . ', "loaded"); throw new RuntimeException("Workspace code was loaded");');
    $inputHashes = array_map(static fn(string $path): string => self::hashFile($path), $this->releaseFiles);
    $command = [PHP_BINARY, '-d', 'memory_limit=128M', dirname(__DIR__, 2) . '/bin/assegai', 'release:check',
      '--inventory=' . $this->inventoryFile, '--modules=' . $this->modulesFile, '--evidence=' . $this->evidenceFile];
    $process = new \Symfony\Component\Process\Process($command, $this->releaseDirectory, ['ASSEGAI_NO_UPDATE_CHECK' => '0'], timeout: 5);
    try {
      $process->run();
      expect($process->getExitCode())->toBe(0)
        ->and($process->getErrorOutput())->toBe('')
        ->and($process->getOutput())->toStartWith('{')
        ->and(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'])->toBe('ready')
        ->and(file_exists($tripwire))->toBeFalse()
        ->and(array_map(static fn(string $path): string => self::hashFile($path), $this->releaseFiles))->toBe($inputHashes);
    } finally {
      unlink($this->releaseDirectory . '/vendor/autoload.php');
      rmdir($this->releaseDirectory . '/vendor');
      if (is_file($tripwire)) { unlink($tripwire); }
    }
  }

  #[DataProvider('casesForRejectsAbsentExtraAndNonAdvancingCanonicalStreamMappings')]
  public function testRejectsAbsentExtraAndNonAdvancingCanonicalStreamMappings(string $case): void
  {
    match ($case) {
      'missing' => $this->canonical['releaseLines']['1.2'] = ['assegaiphp/attributes' => '1.0'],
      'extra' => $this->canonical['releaseLines']['1.2']['assegaiphp/core'] = '1.2',
      'unknown-mode' => $this->canonical['modules']['assegaiphp/attributes']['versioning'] = 'independent',
      'unknown-line' => $this->canonical['releaseLines'] = ['1.1' => $this->canonical['releaseLines']['1.1']],
      'not-advancing' => $this->canonical['releaseLines']['1.2']['assegaiphp/rabbitmq'] = '3.3',
      default => throw new RuntimeException('Unknown controlled fixture case.'),
    };
    $this->writeRelease('modules', $this->canonical);
    $result = (new ReleaseInventoryValidator())->validateFiles($this->inventoryFile, $this->modulesFile);
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('invalid-input');
  }

  public function testChecksDependenciesAgainstTheRequiredModuleStreamRatherThanTheConsumerStream(): void
  {
    $this->releaseEvidence['modules']['assegaiphp/core']['require'] = (object) ['assegaiphp/attributes' => '~1.0.3'];
    $this->releaseEvidence['modules']['assegaiphp/rabbitmq']['require'] = (object) ['assegaiphp/core' => '~1.2.0'];
    $this->writeRelease('evidence', $this->releaseEvidence);
    $validator = new ReleaseInventoryValidator();
    expect($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->isReady)->toBeTrue();
    $this->releaseEvidence['modules']['assegaiphp/core']['require'] = (object) ['assegaiphp/attributes' => '~1.2.0'];
    $this->writeRelease('evidence', $this->releaseEvidence);
    expect(array_column($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->issues, 'code'))->toContain('dependency-line');
  }

  public function testRejectsRangesBelowReviewedMinimumsEvenWithinTheCorrectStream(): void
  {
    self::assertNotSame([], ReleaseInventoryValidator::subsetIssues('~0.11.0', '~0.11.1'));
    self::assertSame([], ReleaseInventoryValidator::subsetIssues('~0.11.1', '~0.11.1'));
    self::assertFalse(ReleaseInventoryValidator::versionAllowed('0.11.0', '~0.11.1'));
    self::assertSame([], ReleaseInventoryValidator::constraintIssues('0.11.2', '~0.11.1', '0.11'));
  }

  public function testIndependentArtifactPatchKeepsTheCompatibilityFloorAndExactPublicationIdentity(): void
  {
    $this->planCoreArtifact('1.2.1', '~1.2.0');
    $this->releaseEvidence['modules']['assegaiphp/console']['require'] = (object) ['assegaiphp/core' => '~1.2.0'];
    $this->writeRelease('evidence', $this->releaseEvidence);
    $validator = new ReleaseInventoryValidator();
    self::assertTrue($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->isReady);
    $manifest = $this->writeRelease('manifest', ['name' => 'assegaiphp/console', 'require' => (object) ['assegaiphp/core' => '~1.2.0']]);
    self::assertSame('passed', $validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest)['status']);
    $inventory = $validator->loadInventory($this->inventoryFile, $this->modulesFile);
    self::assertSame([], $inventory->requirementIssues('assegaiphp/core', '~1.2.9'));
    self::assertSame(['require' => ['assegaiphp/core' => '~1.2.9']], \Assegai\Console\Util\ComposerManifest::ensureRecommendedRequirement(
      ['require' => ['assegaiphp/core' => '~1.2.9']], 'assegaiphp/core', $inventory->recommendedConstraints()['assegaiphp/core']));
    $core = $this->writeRelease('manifest', ['name' => 'assegaiphp/core']);
    self::assertSame('passed', $validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $core, 'refs/tags/1.2.1')['status']);
    self::assertSame('blocked', $validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $core, 'refs/tags/1.2.0')['status']);
    $this->releaseEvidence['publication']['assegaiphp/core']['version'] = '1.2.0';
    $this->writeRelease('evidence', $this->releaseEvidence);
    self::assertContains('package-publication', array_column($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Publish, $this->evidenceFile)->issues, 'code'));
    $this->releaseEvidence['composer']['versions']['assegaiphp/core'] = '1.2.0';
    $this->writeRelease('evidence', $this->releaseEvidence);
    self::assertContains('composer-versions', array_column($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->issues, 'code'));
  }

  public function testIntentionalFloorAdvanceStillRejectsOlderDependentRequirements(): void
  {
    $this->planCoreArtifact('1.2.1', '~1.2.1');
    $manifest = $this->writeRelease('manifest', ['name' => 'assegaiphp/console', 'require' => (object) ['assegaiphp/core' => '~1.2.0']]);
    self::assertSame('blocked', (new ReleaseInventoryValidator())->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest)['status']);
  }

  public function testContainmentUsesCompleteComposerSetsIncludingGapsAndUnions(): void
  {
    $envelope = '>=0.11.0 <0.11.2 || >=0.11.3 <0.12.0';
    self::assertSame([], ReleaseInventoryValidator::constraintIssues('0.11.3', $envelope, '0.11'));
    self::assertSame([], ReleaseInventoryValidator::subsetIssues('~0.11.3', $envelope));
    self::assertNotSame([], ReleaseInventoryValidator::subsetIssues('~0.11.0', $envelope));
    self::assertFalse(ReleaseInventoryValidator::versionAllowed('0.11.2', $envelope));
    self::assertFalse(ReleaseInventoryValidator::versionAllowed('0.11.3-beta', $envelope));
  }

  public function testLoadsOfflineDataThroughTheSameReaderAndRejectsChangedBundleBytes(): void
  {
    $directory = $this->releaseDirectory . '/bundle';
    mkdir($directory);
    copy($this->inventoryFile, $directory . '/inventory.json');
    copy($this->modulesFile, $directory . '/modules.json');
    file_put_contents($directory . '/source.json', json_encode(['schema' => 1, 'repository' => 'assegaiphp/core',
      'sourceSha' => str_repeat('a', 40), 'canonicalModulesPath' => 'meta/framework-modules.json', 'inventoryPath' => 'meta/releases/test.json',
      'inventorySha256' => 'sha256:' . self::hashFile($this->inventoryFile), 'canonicalModulesSha256' => 'sha256:' . self::hashFile($this->modulesFile)], JSON_THROW_ON_ERROR));
    expect(\Assegai\Console\Releases\BundledRelease::load($directory)->recommendedConstraints())->toEqual(array_map(static fn(array $plan): string => $plan['installConstraint'], $this->releasePlan['packages']));
    file_put_contents($directory . '/inventory.json', "\n", FILE_APPEND);
    expect(fn() => \Assegai\Console\Releases\BundledRelease::load($directory))->toThrow(RuntimeException::class, 'hashes');
  }

  public function testAccountsForUnreleasedModulesWithoutRecommendingOrPublishingThem(): void
  {
    $name = 'assegaiphp/draft';
    $this->canonical['modules'][$name] = ['repository' => $name, 'directory' => 'draft', 'kind' => 'library', 'versioning' => 'framework', 'publication' => 'unreleased'];
    $this->releasePlan['packages'][$name] = ['version' => '1.2.0', 'installConstraint' => '~1.2.0'];
    $this->releaseEvidence['modules'][$name] = ['sourceSha' => sha1($name), 'dependencySha256' => 'sha256:' . hash('sha256', $name),
      'require' => new stdClass(), 'require-dev' => new stdClass(), 'checks' => new stdClass()];
    $this->releaseEvidence['membership']['unpublishedModules'] = [$name];
    $this->writeRelease('inventory', $this->releasePlan);
    $this->writeRelease('modules', $this->canonical);
    $this->releaseEvidence['inventorySha256'] = $this->releaseEvidence['website']['content']['inventorySha256'] = 'sha256:' . self::hashFile($this->inventoryFile);
    $this->releaseEvidence['canonicalModulesSha256'] = $this->releaseEvidence['membership']['canonicalModulesSha256'] = $this->releaseEvidence['website']['content']['canonicalModulesSha256'] = 'sha256:' . self::hashFile($this->modulesFile);
    $this->writeRelease('evidence', $this->releaseEvidence);
    $validator = new ReleaseInventoryValidator();
    $result = $validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile);
    expect($result->isReady)->toBeTrue();
    self::assertArrayNotHasKey($name, ($result->inventory ?? throw new RuntimeException('Missing fixture inventory.'))->recommendedConstraints());
    $this->releaseEvidence['modules']['assegaiphp/core']['require'] = (object) [$name => '~1.2.0'];
    $this->releaseEvidence['cli']['catalog'][$name] = '~1.2.0';
    $this->writeRelease('evidence', $this->releaseEvidence);
    expect(array_column($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->issues, 'code'))->toContain('unreleased-dependency', 'cli-plan');
  }

  public function testRunsTheStandalonePackagePolicyWithOnlyTheExplicitlySuppliedRealSemverLoader(): void
  {
    $filename = (new ReflectionClass(\Composer\Semver\VersionParser::class))->getFileName();
    $semverDirectory = dirname($filename ?: throw new RuntimeException('Missing Semver source.'));
    $autoload = $this->releaseDirectory . '/semver-autoload.php';
    file_put_contents($autoload, '<?php spl_autoload_register(static function(string $class): void { $prefix="Composer\\\\Semver\\\\"; if(str_starts_with($class,$prefix)){require ' . var_export($semverDirectory, true) . ' . "/" . str_replace("\\\\", "/", substr($class,strlen($prefix))) . ".php";}});');
    $manifest = $this->writeRelease('manifest', ['name' => 'assegaiphp/core', 'require' => (object) ['assegaiphp/attributes' => '~1.0.3']]);
    $process = new \Symfony\Component\Process\Process([PHP_BINARY, dirname(__DIR__, 2) . '/tools/release-check.php', '--mode=package',
      '--inventory=' . $this->inventoryFile, '--modules=' . $this->modulesFile, '--manifest=' . $manifest, '--semver-autoload=' . $autoload], $this->releaseDirectory, timeout: 5);
    $process->run();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($process->getExitCode())->toBe(0)->and($process->getErrorOutput())->toBe('')
      ->and($result)->toMatchArray(['kind' => 'package-policy', 'status' => 'passed']);
    self::assertArrayNotHasKey('isReady', $result);
    $this->writeRelease('manifest', ['name' => 'assegaiphp/core', 'require' => (object) ['assegaiphp/attributes' => '~1.2.0']]);
    $process->run();
    expect($process->getExitCode())->toBe(1)
      ->and(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'])->toBe('blocked');
  }

  #[DataProvider('websiteLockCompatibility')]
  public function testRequiresTheActualCleanWebsiteLockToConsumeTheReviewedVersionsBeforePublish(string $version, string $requirement, string $envelope, bool $compatible): void
  {
    $this->planCoreArtifact('1.2.2', $envelope);
    $website = $this->releaseDirectory . '/website';
    mkdir($website);
    $names = new \Symfony\Component\Process\Process(['git', 'rev-parse', '--local-env-vars'], timeout: 5);
    $names->mustRun();
    $environment = [];
    foreach (explode("\n", trim($names->getOutput())) as $name) { $environment[trim($name)] = false; }
    // Fixture mutations must target their own repository when tests run in a hook.
    $git = static function (array $arguments) use ($website, $environment): string {
      $process = new \Symfony\Component\Process\Process(['git', '-C', $website, ...$arguments], env: $environment, timeout: 5);
      $process->mustRun();
      return trim($process->getOutput());
    };
    $commit = static function () use ($git): void {
      $git(['add', 'composer.json', 'composer.lock']);
      $git(['-c', 'user.name=Release Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'test: record controlled Website dependency fixture']);
    };
    $bind = function () use ($git, $website): void {
      $source = $git(['rev-parse', 'HEAD']);
      $lockHash = 'sha256:' . self::hashFile($website . '/composer.lock');
      foreach (['content', 'runtime', 'render'] as $kind) {
        $this->releaseEvidence['website'][$kind]['sourceSha'] = $source;
        $this->releaseEvidence['website'][$kind]['composerLockSha256'] = $lockHash;
      }
      $this->releaseEvidence['website']['sourceSha'] = $source;
      $this->releaseEvidence['website']['composerLockSha256'] = $lockHash;
      $this->writeRelease('evidence', $this->releaseEvidence);
    };
    file_put_contents($website . '/composer.json', json_encode(['name' => 'assegaiphp/website', 'require' => ['assegaiphp/core' => $requirement]], JSON_THROW_ON_ERROR));
    $lock = ['packages' => [['name' => 'assegaiphp/core', 'version' => $version]], 'packages-dev' => []];
    file_put_contents($website . '/composer.lock', json_encode($lock, JSON_THROW_ON_ERROR));
    $git(['init', '--initial-branch=fixture']);
    $commit();
    $bind();
    $copy = $this->releaseDirectory . '/source-copy';
    mkdir($copy);
    copy($website . '/composer.json', $copy . '/composer.json');
    $checkout = \Assegai\Console\Releases\SourceCheckout::at($website);
    $checkout->verifyFiles($git(['rev-parse', 'HEAD']), [$copy . '/composer.json'], $copy);
    file_put_contents($copy . '/composer.json', "\n", FILE_APPEND);
    expect(fn() => $checkout->verifyFiles($git(['rev-parse', 'HEAD']), [$copy . '/composer.json'], $copy))
      ->toThrow(RuntimeException::class, 'pinned source');
    $validator = new ReleaseInventoryValidator();
    self::assertSame($compatible, $validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Publish, $this->evidenceFile, $website . '/composer.json', $website . '/composer.lock')->isReady);
    $lock['packages'][0]['version'] = '1.1.9';
    file_put_contents($website . '/composer.lock', json_encode($lock, JSON_THROW_ON_ERROR));
    expect($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Publish, $this->evidenceFile, $website . '/composer.json', $website . '/composer.lock')->isReady)->toBeFalse();
    $commit();
    $bind();
    expect($validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Prepare, $this->evidenceFile)->isReady)->toBeTrue();
    $result = $validator->validateFiles($this->inventoryFile, $this->modulesFile, ReleaseStage::Publish, $this->evidenceFile, $website . '/composer.json', $website . '/composer.lock');
    expect($result->isReady)->toBeFalse()->and(array_column($result->issues, 'code'))->toContain('website-dependencies');
  }

  /** @param array<string, mixed> $value */
  private function writeRelease(string $name, array $value): string
  {
    $path = $this->releaseDirectory . '/' . $name . '.json';
    file_put_contents($path, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $this->releaseFiles[$name] = $path;
    return $path;
  }

  private function planCoreArtifact(string $version, string $envelope): void
  {
    $name = 'assegaiphp/core';
    $this->releasePlan['packages'][$name] = ['version' => $version, 'installConstraint' => $envelope];
    $this->releaseEvidence['composer']['versions'][$name] = $version;
    $this->releaseEvidence['publication'][$name]['version'] = $version;
    foreach (['catalog', 'defaults', 'update'] as $kind) { $this->releaseEvidence['cli'][$kind][$name] = $envelope; }
    $this->writeRelease('inventory', $this->releasePlan);
    $hash = 'sha256:' . self::hashFile($this->inventoryFile);
    $this->releaseEvidence['inventorySha256'] = $this->releaseEvidence['website']['content']['inventorySha256'] = $hash;
    $this->writeRelease('evidence', $this->releaseEvidence);
  }

  /** @return list<array{string, string, string, bool}> */
  public static function websiteLockCompatibility(): array
  {
    return [
      ['1.2.2', '~1.2.0', '~1.2.0', true],
      ['1.2.0', '~1.2.0', '~1.2.0', true],
      ['1.2.9', '~1.2.0', '~1.2.0', true],
      ['1.1.0', '~1.2.0', '~1.2.0', false],
      ['1.2.0', '~1.2.1', '~1.2.1', false],
      ['1.2.1', '~1.2.2', '~1.2.0', false],
      ['1.2.3-beta', '~1.2.0', '~1.2.0', false],
    ];
  }

  public function testChecksActualReleaseAndTagVersionsWhenTheManifestOmitsVersion(): void
  {
    $manifest = $this->writeRelease('manifest', ['name' => 'assegaiphp/core']);
    $validator = new ReleaseInventoryValidator();
    expect($validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest, 'refs/tags/v1.2.0')['status'])->toBe('passed')
      ->and($validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest, 'refs/heads/develop')['status'])->toBe('passed');
    foreach (['refs/tags/1.3.0', 'refs/heads/release/1.1.0'] as $reference) {
      expect(array_column($validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest, $reference)['issues'], 'code'))->toContain('release-reference');
    }
  }

  public function testBlocksReleaseAndTagRefsForUnreleasedModulesWhileAllowingDevelopmentObservations(): void
  {
    $this->canonical['modules']['assegaiphp/core']['publication'] = 'unreleased';
    $this->writeRelease('modules', $this->canonical);
    $manifest = $this->writeRelease('manifest', ['name' => 'assegaiphp/core']);
    $validator = new ReleaseInventoryValidator();
    self::assertSame('passed', $validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest, 'refs/heads/develop')['status']);
    foreach (['refs/tags/1.2.0', 'refs/heads/release/1.2.0'] as $reference) {
      $result = $validator->validateManifestFiles($this->inventoryFile, $this->modulesFile, $manifest, $reference);
      self::assertSame('blocked', $result['status']);
      self::assertContains('unreleased-publication', array_column($result['issues'], 'code'));
    }
  }

  private static function hashFile(string $filename): string
  {
    return hash_file('sha256', $filename) ?: throw new RuntimeException('Cannot hash the controlled fixture.');
  }

  /** @return list<array{string, string}> */
  public static function casesForRejectsMajorMinorDriftAndConstraintsPermittingAnotherMinor(): array
  {
    return [['0.11.0', '^0.11.0'], ['2.0.0', '~2.0.0'], ['1.2.3', '^1.2.0'], ['1.2.3', '>=1.2'], ['1.2.3-beta', '~1.2.0']];
  }

  /** @return list<array{string, mixed}> */
  public static function casesForRejectsContradictoryWebsiteReceipts(): array
  {
    return [['passed', false], ['errors', ['Source changed during content checks.']]];
  }

  /** @return list<array{string}> */
  public static function casesForRejectsAbsentExtraAndNonAdvancingCanonicalStreamMappings(): array
  {
    return array_map(static fn(string $case): array => [$case], ['missing', 'extra', 'unknown-mode', 'unknown-line', 'not-advancing']);
  }
}
