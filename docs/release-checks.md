# Coordinated release checks

Core owns `meta/framework-modules.json` and `meta/releases/<line>.json`. Console owns the reader and policy checks in `src/Releases`. Do not maintain a second package/version map.

Console distributes byte-identical copies under `meta/release`. `source.json` records the owning Core commit, original paths and SHA-256 hashes. Refresh the copies from those explicit files and verify both against the named commit. Normal commands read this offline snapshot; they never discover sibling checkouts or query a moving branch.

Framework modules follow the target framework major/minor. Established separate streams use the exact canonical mapping. Every module requires a versioning mode and publication lifecycle. Unreleased modules remain planned and checked, but cannot become public recommendations or dependencies of released modules. First publication requires updating canonical lifecycle and completing the same checks.

`version` identifies the exact planned release artifact. `installConstraint` defines its reviewed compatibility envelope and minimum; the artifact must be admitted by that envelope. Dependent requirements and preserved application ranges must be subsets of the complete envelope. An independent artifact patch does not raise that minimum. Candidate resolution, release/tag refs and publication observations still name the exact planned artifact.

## Standalone consumer contract

Use a reviewed, pinned Console source checkout and an explicit autoloader supplying `composer/semver`. No Console installation, Common dependency, application bootstrap or network lookup is required by this entrypoint:

```sh
php /pinned-console/tools/release-check.php \
  --mode=package \
  --semver-autoload=/policy-tools/vendor/autoload.php \
  --inventory=/pinned-core/meta/releases/0.11.json \
  --modules=/pinned-core/meta/framework-modules.json \
  --manifest=/candidate/composer.json \
  --format=json
```

Package mode returns `kind: package-policy`, `status: passed|blocked`, exact input hashes and issues. It checks membership, an explicit manifest version when present, first-party dependency streams and minimums. A passed package policy result does **not** establish complete release readiness. Run it for each candidate in local preparation and the repository's required checks. The caller must pin the actual reader/data/tool sources and configure enforcement; adding this entrypoint does not activate remote branch protection or CI by itself.

### Hooks and GitHub checks

Install the single semver-only tool lock from the pinned Console source:

```sh
composer --working-dir=/pinned-console/tools/policy install --no-plugins --no-scripts --no-interaction --no-progress
```

Use `--mode=consumer` for enforcement. Supply `--console-root=/pinned-console --console-ref=<full-commit> --core-root=/pinned-core --core-ref=<full-commit>` alongside the package-mode arguments. Set `--semver-autoload=/pinned-console/tools/policy/vendor/autoload.php` and supply the actual checked `--ref=refs/heads/develop` (or actual pre-push destination/GitHub ref). Missing pins, inputs that differ from their pinned source, and release/tag versions different from the module plan fail closed. Ordinary develop/main checks remain package-policy observations; they do not authorize publishing.

The shared composite action lives at `.github/actions/package-policy`. Call it from a full reviewed Console commit, supplying its explicit `console-root`, `console-ref`, `core-root`, `core-ref`, `inventory`, `modules`, `manifest` and `semver-autoload` inputs. Its `ref` input defaults to the actual GitHub ref. The action performs no checkout, dependency installation or network lookup; callers supply pinned checkouts and reuse the locked tool installation/cache. Both hooks and the action invoke the same consumer mode. Existing repository checks and publishing restrictions remain required.

Console's CI calls the pinned Core reusable workflow before its existing build jobs, including release-branch and tag pushes. Its tracked pre-push hook runs the same policy before Composer analysis/tests. For local hook use, explicitly provide `ASSEGAI_RELEASE_CONSOLE` and `ASSEGAI_RELEASE_CORE` as the reviewed checkout roots. The hook checks the actual destination ref and committed manifest; annotated tags are resolved to their commit before checking source identity. Tracking a hook does not install it: inspect the effective hooks directory before activation, preserving every existing guard. Unpublished source pins cannot run remotely until those commits are available under the authorized publishing workflow.

Source observations clear Git's repository-local environment in their own child processes, using the names reported by `git rev-parse --local-env-vars`. This allows actual Git hooks to inspect the explicitly supplied foreign checkouts while preserving the hook's own context and normal subprocess inheritance. Native contained file paths are converted to Git's `/` path form after containment checks.

## Full inventory stages

The same standalone entrypoint accepts `--mode=prepare|publish` and `--evidence=/owner-evidence.json`. An installed Console provides the equivalent `assegai release:check --stage=prepare|publish` command with explicit `--inventory`, `--modules` and `--evidence` files.

Prepare requires canonical membership reconciliation, exact source/dependency identities, package and release-note checks, complete candidate Composer resolution, observed CLI catalog/default/update plans and Website content/runtime/render receipts. Generated check receipts must name the actual checked candidates and inputs. A URL, planned status or hand-written success flag is insufficient evidence. Check production provenance and retain the receipts with the reviewed candidate.

Publish additionally requires fresh stable-package observations matching reviewed versions and tag/source identities. It requires `--website-manifest=/website/composer.json --website-lock=/website/composer.lock`. The checker reads those actual files in a clean checkout and verifies their bytes against the recorded commit and lock digest. Present first-party locked versions must be stable and satisfy the reviewed envelope and actual manifest requirements. Their compatible patch versions may differ from the planned artifacts; source/lock/runtime/render receipts still bind the actual Website graph. An old minor or a version below an explicit floor cannot satisfy Publish.

All hashes use `sha256:<64 lowercase hex digits>`; Git object identities are bare 40/64-digit lowercase hex. Every command emits JSON and exits nonzero when blocked. These checks read files and Git metadata; they do not install dependencies, write artifacts, publish refs or grant release authority.

## Dependency activation

Candidate policy checks can run before target packages exist. Their success does not make those packages available. Qualify candidate resolution separately, then activate shipping locks against actual parent releases in dependency order. Do not ship fabricated versions or path-repository locks. Andrew owns release branches, tags and releases; publishing follows the canonical repository workflow and its separate approvals.

`updates:scaffold` remains internal. It requires an explicit existing `--output` directory outside source repositories and Composer projects, produces incomplete draft artifacts, and refuses existing targets. It no longer writes directly into Website catalogs or Core documentation. The Website owner reviews and admits content through the coordinated release workflow.
