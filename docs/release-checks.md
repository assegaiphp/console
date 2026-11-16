# Coordinated release checks

Core owns `meta/framework-modules.json` and `meta/releases/<line>.json`. Console owns the reader and policy checks in `src/Releases`. Do not maintain a second package/version map.

Console distributes byte-identical copies under `meta/release`. `source.json` records the owning Core commit, original paths and SHA-256 hashes. Refresh the copies from those explicit files and verify both against the named commit. Normal commands read this offline snapshot; they never discover sibling checkouts or query a moving branch.

Framework modules follow the target framework major/minor. Established separate streams use the exact canonical mapping. Every module requires a versioning mode and publication lifecycle. Unreleased modules remain planned and checked, but cannot become public recommendations or dependencies of released modules. First publication requires updating canonical lifecycle and completing the same checks.

`version` identifies the exact coordinated release artifact. `installConstraint` is the current train’s new-install recommendation and qualification range; it admits that artifact. Each consumer’s Composer manifest owns its dependency support. A dependency may retain reviewed older support alongside acceptance of the target artifact. An independent artifact patch does not change a consumer’s support contract. Candidate resolution, release/tag refs and publication observations still name the exact planned artifact.

## Standalone consumer contract

Use a reviewed, pinned Console source checkout and an explicit autoloader supplying `composer/semver`. No Console installation, Common dependency, application bootstrap or network lookup is required by this entrypoint:

```sh
php /pinned-console/tools/release-check.php \
  --mode=package --purpose=current-train \
  --semver-autoload=/policy-tools/vendor/autoload.php \
  --inventory=/pinned-core/meta/releases/0.11.json \
  --modules=/pinned-core/meta/framework-modules.json \
  --manifest=/candidate/composer.json \
  --format=json
```

A manifest-only current-train check returns schema 2, `kind: package-policy`, `purpose: current-train`, `qualification: structural`, `status: passed|blocked` and `releaseReady: false`, with exact input hashes and issues. It checks canonical membership/lifecycle, the module’s own artifact/ref and bounded parsed dependency ranges accepting the planned dependency artifacts. A retained union can pass this structural check without proving compatibility with its additional intervals. Full release qualification requires the owner evidence below.

`--purpose=published-compatibility` checks ordinary membership/range syntax without requiring future target acceptance. Without owner evidence its result is `qualification: observation`, `status: observed`; it makes no verified compatibility claim. With explicit source-bound owner evidence it can return `qualification: consumer-support`, `status: compatible`. Neither result can satisfy current-train enforcement or become release-ready. Every standalone invocation requires an explicit purpose; Prepare and Publish accept only `current-train`. The installed full-stage command has that same current-train purpose.

The caller must pin actual reader/data/tool sources and configure enforcement; adding this entrypoint does not activate remote branch protection or CI by itself.

### Hooks and GitHub checks

Install the single semver-only tool lock from the pinned Console source:

```sh
composer --working-dir=/pinned-console/tools/policy install --no-plugins --no-scripts --no-interaction --no-progress
```

Use `--mode=consumer --purpose=current-train` for enforcement. Supply `--console-root=/pinned-console --console-ref=<full-commit> --core-root=/pinned-core --core-ref=<full-commit>` alongside the package-mode arguments. Set `--semver-autoload=/pinned-console/tools/policy/vendor/autoload.php` and supply the actual checked `--ref=refs/heads/develop` (or actual pre-push destination/GitHub ref). Missing pins, inputs that differ from their pinned source, and release/tag versions different from the module plan fail closed. Ordinary develop/main checks remain package-policy observations; they do not authorize publishing.

The shared composite action lives at `.github/actions/package-policy`. Call it from a full reviewed Console commit, supplying its explicit `console-root`, `console-ref`, `core-root`, `core-ref`, `inventory`, `modules`, `manifest` and `semver-autoload` inputs. Its `ref` input defaults to the actual GitHub ref. The action performs no checkout, dependency installation or network lookup; callers supply pinned checkouts and reuse the locked tool installation/cache. Both hooks and the action hardcode the current-train purpose. An optional generated owner receipt can be supplied with the action’s `evidence` input or the hook’s `ASSEGAI_RELEASE_EVIDENCE`; ordinary clean-checkout structural checks need no receipt containing their own future commit. Existing repository checks and publishing restrictions remain required.

Console's CI calls the pinned Core reusable workflow before its existing build jobs, including release-branch and tag pushes. Its tracked pre-push hook runs the same policy before Composer analysis/tests. For local hook use, explicitly provide `ASSEGAI_RELEASE_CONSOLE` and `ASSEGAI_RELEASE_CORE` as the reviewed checkout roots. The hook checks the actual destination ref and committed manifest; annotated tags are resolved to their commit before checking source identity. Tracking a hook does not install it: inspect the effective hooks directory before activation, preserving every existing guard. Unpublished source pins cannot run remotely until those commits are available under the authorized publishing workflow.

Source observations clear Git's repository-local environment in their own child processes, using the names reported by `git rev-parse --local-env-vars`. This allows actual Git hooks to inspect the explicitly supplied foreign checkouts while preserving the hook's own context and normal subprocess inheritance. Native contained file paths are converted to Git's `/` path form after containment checks.

## Full inventory stages

The same standalone entrypoint accepts `--mode=prepare|publish --purpose=current-train` and `--evidence=/owner-evidence.json`. An installed Console provides the equivalent `assegai release:check --stage=prepare|publish` command with explicit `--inventory`, `--modules` and `--evidence` files.

Aggregate evidence and full-stage results use schema 2 with `purpose: current-train`. Older evidence schemas and other purposes fail closed. Results distinguish `qualification: release` and `releaseReady` from structural observations. The inventory, canonical modules and Website content protocol retain schema 1.

Prepare requires canonical membership reconciliation, exact source/dependency identities, package and release-note checks, complete candidate Composer resolution, observed CLI catalog/default/update plans and Website content/runtime/render receipts. Generated check receipts must name the actual checked candidates and inputs. A URL, planned status or hand-written success flag is insufficient evidence. Check production provenance and retain the receipts with the reviewed candidate.

Publish additionally requires fresh stable-package observations matching reviewed versions and tag/source identities. It requires `--website-manifest=/website/composer.json --website-lock=/website/composer.lock`. The checker reads those actual files in a clean checkout and verifies their bytes against the recorded commit and lock digest. Present first-party locked versions must be stable and satisfy the current qualification range and actual manifest requirements. Their compatible patch versions may differ from the planned artifacts; source/lock/runtime/render receipts still bind the actual Website graph. An old minor or a version below an explicit floor cannot satisfy Publish.

All hashes use `sha256:<64 lowercase hex digits>`; Git object identities are bare 40/64-digit lowercase hex. Every command emits JSON and exits nonzero when blocked. These checks read files and Git metadata; they do not install dependencies, write artifacts, publish refs or grant release authority.

## Consumer support evidence

Aggregate `modules[consumer]` owner receipts include `sourceSha`, `manifestSha256`, `dependencySha256`, actual `require`/`require-dev` maps and completed checks. Package and release-note checks bind the same source, manifest and dependencies. Additional dependency support uses one parent-bound structure:

- `dependencySupport[require|require-dev][provider].requirement`: the literal consumer declaration.
- `contracts`: reviewed intervals with `interval`, `historySha256` and `apiBasisSha256`. The historical published consumer requirement can be a retained support contract after reviewing its provider release history and used APIs.
- `samples`: separately executed provider `version`/`sourceSha`, actual `lockSha256`/`graphSha256`, and `check` containing `status: passed` and the executed `environment`.

The consumer identity, manifest and dependency key inherit from their containing owner receipt. Review-basis and executed-graph digests describe different inputs. Each reviewed interval needs an executed sample inside the consumer requirement. A sample reports only the version/environment actually executed; it does not manufacture proof that every future patch ran. Composer interval containment, including unions and gaps, requires the complete claim to fit the union of reviewed contracts and current qualification range. Ordinary verified compatibility cannot substitute an unexecuted recommendation for support evidence. Empty, unbounded, development and unsupported stability ranges fail closed.

Optional standalone verification uses `--evidence=/external/consumer-owner.json`. Its parent contains `schema: 2`, the requested `purpose`, `package`, `sourceSha`, `manifestSha256` and the same `dependencySupport` object. The reader verifies actual clean consumer HEAD and committed Composer bytes. Keep generated evidence outside that consumer checkout. Evidence for one consumer cannot authorize another consumer’s declaration. Do not commit a receipt naming its own future HEAD.

CLI catalog/default observations retain current recommendations. `cli.update` records the resulting requirement map; `cli.updateOperation` records `intent` (`preserve-support` or `coordinated-upgrade`), the original `before` map, `beforeManifestSha256`, `afterManifestSha256`, `previewSha256` and `removalsApproved`. The preview digest is SHA-256 of the JSON object `{before,after}`, encoded without escaped slashes in the recorded map order. Its `after` map is `cli.update`. Preserving support cannot rewrite existing declarations. A coordinated migration must preview removed support and record explicit approval. These observations test command behavior; they do not qualify an application’s dependency graph.

`assegai add` preserves existing declared support and uses current recommendations for missing packages. Update planning preserves an existing range that contains the recommendation, including a union. Changes removing previously admitted versions require a visible preview and confirmation (or explicit `--yes`), including minimum-runtime changes within the same framework line. Validation occurs before project writes. New-project Core defaults and consumers requiring the source-directory correction retain the actual `~0.11.1` capability floor.

## Dependency activation

Candidate policy checks can run before target packages exist. Their success does not make those packages available. Qualify candidate resolution separately, then activate shipping locks against actual parent releases in dependency order. Do not ship fabricated versions or path-repository locks. Andrew owns release branches, tags and releases; publishing follows the canonical repository workflow and its separate approvals.

`updates:scaffold` remains internal. It requires an explicit existing `--output` directory outside source repositories and Composer projects, produces incomplete draft artifacts, and refuses existing targets. It no longer writes directly into Website catalogs or Core documentation. The Website owner reviews and admits content through the coordinated release workflow.
