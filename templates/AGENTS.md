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
