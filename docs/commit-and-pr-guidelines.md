# Commit And PR Guidelines

This note turns the release playbook into a simple day-to-day format for commits and pull requests in `assegaiphp/console`.

The goal is not to add ceremony.

The goal is to make the history easier to understand and make milestone scope easier to protect.

## Commit guideline

Keep commits:

- small
- single-purpose
- easy to review
- easy to revert

### Recommended format

```text
type(scope): short summary
```

Examples:

```text
fix(console): route migration down through the SQLite migrator
feature(console): discover package commands from installed extensions
test(console): cover generated project composer defaults
docs(console): define the shared PR and commit workflow
```

### Recommended commit types

- `fix`
- `feature`
- `docs`
- `test`
- `refactor`
- `chore`

### Optional commit body

Use a body when it helps explain:

- why the change exists
- what constraint or bug it addresses
- how it was verified

Example:

```text
fix(console): route migration down through the SQLite migrator

Use the proper migrator abstraction for SQLite and PostgreSQL rollback paths.
Add regression coverage for migration:down selection.
Keep analyze and test lanes green.
```

### Commit rule of thumb

If the summary needs "and" to describe two unrelated changes, it probably wants two commits.

## Pull request guideline

Every PR should clearly answer:

1. which milestone it belongs to
2. why it belongs there
3. what changed
4. what did not change
5. how it was verified

## Recommended PR structure

### Title

Keep the PR title short and outcome-focused.

Examples:

```text
Fix SQLite migration down selection
Add package-driven ORM installer discovery
Correct generated composer defaults for new apps
```

### Body template

```md
## Milestone
0.8.0

## Type
bug
test

## Why this belongs in this milestone
Explain why this work belongs here instead of another milestone.

## What changed
- change one
- change two

## What did not change
- boundary one
- boundary two

## Verification
- `composer test`
- `composer analyze`

## User impact
- short user-facing effect

## Release notes
Not needed

## Upgrade notes
Not needed
```

## Milestone rule

If a PR does not clearly belong to a milestone, it should not be merged until that is resolved.

## Release notes rule

Use these simple defaults:

- `Release notes: Not needed` for internal refactors, tests, and narrow fixes
- `Release notes: Needed` when the change is user-visible enough to matter in a release summary
- `Upgrade notes: Needed` when a user may need to change code, config, workflow, or expectations

## Copilot and GitHub setup

There are two useful automation hooks here:

### 1. Repository Copilot instructions

GitHub Copilot supports repository-wide custom instructions through:

- `.github/copilot-instructions.md`

That is the right place to teach Copilot the commit and PR format for this repository.

GitHub Docs:

- [Adding repository custom instructions for GitHub Copilot](https://docs.github.com/en/copilot/customizing-copilot/adding-custom-instructions-for-github-copilot?tool=visualstudio)

### 2. GitHub PR templates

GitHub supports repository PR templates through:

- `.github/pull_request_template.md`

GitHub Docs:

- [Creating a pull request template for your repository](https://docs.github.com/en/enterprise-server%403.14/communities/using-templates-to-encourage-useful-issues-and-pull-requests/creating-a-pull-request-template-for-your-repository)

### 3. Organization-wide defaults

If you want the same PR template across multiple repositories, GitHub supports default community health files through a public or internal `.github` repository owned by the organization or account.

That is the cleanest way to scale templates across all Assegai repos later.

GitHub Docs:

- [Creating a default community health file](https://docs.github.com/en/github/building-a-strong-community/creating-a-default-community-health-file)
