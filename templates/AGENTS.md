# AGENTS.md

Guidance for coding agents working in this AssegaiPHP application.

## Project Shape

This is an AssegaiPHP application scaffolded with `assegai new`.

- Application source lives in `src/`.
- The root module is `src/AppModule.php`.
- Controllers, services, resources, DTOs, entities, modules, and components should stay inside the feature area they belong to.
- Shared app configuration belongs in `config/default.php`.
- Sensitive local overrides belong in `config/secure.php`.
- Environment-specific values should come from `.env` through `env(...)` calls.

## Working Rules

- Do not edit `vendor/` or generated dependency files directly.
- Do not commit secrets, private keys, local passwords, database dumps, or machine-specific credentials.
- Prefer the Assegai CLI generators when adding framework artifacts so modules stay wired consistently.
- Keep generated artifacts near the closest owning module instead of defaulting everything to the root module.
- Preserve existing naming and namespace conventions when extending a feature.
- Prioritize correctness, completeness, and accuracy over speed.
- Treat changes as global within the application until the surrounding code proves they are isolated.
- Inspect all affected controllers, services, modules, DTOs, entities, traits, migrations, config files, README steps, and runtime commands before handing off.
- Do not stop at the first visible symptom. Update every related file and verify the behavior through the same command or endpoint a user will run.

## Migrations and Schema Changes

- Derive SQL from the full entity model, including inherited traits such as `ChangeRecorderTrait`, ORM attributes, defaults, nullability, relations, indexes, and seed data.
- Update both `up.sql` and `down.sql` for each affected migration.
- Check every related table when a trait, relation, or shared convention changes.
- For SQLite, apply the migration to an actual database and inspect the schema with `PRAGMA table_info(...)`.
- If a migration seeds data used by an endpoint, verify the endpoint after running the migration.

## Common Commands

Start the app:

```bash
assegai serve
```

Run tests:

```bash
assegai test
```

Generate a resource:

```bash
assegai generate resource users
```

Generate a flat artifact in the current target path when appropriate:

```bash
assegai generate component app --flat
```

## Verification

Before handing off changes, run the most relevant checks available in this project.

```bash
composer validate
composer test
```

If static analysis is configured, also run:

```bash
composer analyze
```

## Notes for Future Agents

- Keep views focused on presentation. Prepare data in the service or component class before passing it to the view.
- Keep public assets under `public/`.
- Keep source-bearing files out of `public/`.
- Treat missing public assets and unknown routes as normal HTTP 404s unless there is evidence of a server-side failure.
