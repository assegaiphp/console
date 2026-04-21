# AssegaiPHP Console 0.8.4

`0.8.4` is the scaffolding hardening and shell safety release.

This is a focused patch on top of `0.8.3`. It tightens shell-argument handling in the `serve` command, hardens the generated front controller's asset-serving logic, makes component schematic module lookup more reliable in nested directory trees, and introduces a tracked `secure.php` configuration template that is automatically populated with the correct user-entity class constant during project setup.

## Highlights

### Shell injection prevention in the `serve` command
Two unquoted command interpolations in the `serve` command have been replaced with explicit `escapeshellarg()` calls:

* The browser-open path (`sensible-browser`) now passes the full URL as a properly quoted shell argument instead of embedding it inline.
* The PHP built-in server path (`php -S`) now passes the host:port URI through `escapeshellarg()` instead of interpolating it directly into the command string.

Host validation has also been rewritten. The previous check only tested for an empty string. The new `isValidServeHost()` method rejects inputs containing whitespace or shell-significant characters, and accepts only `localhost`, valid IPv4 addresses, valid IPv6 addresses (bare or bracket-wrapped), and well-formed hostnames. URI construction has been moved to after host validation so that an invalid host cannot produce a partial `$uri` that leaks into any error path.

IPv6 addresses passed as a bare string are now automatically bracket-wrapped in the URI so `php -S` receives the correct `[::1]:8080` form.

### Hardened generated front controller
The `index.php` template now uses an explicit allowlist of permitted static-asset extensions rather than passing everything that is not a PHP file through to the filesystem. Files with no extension are blocked unless they fall under `/.well-known/`, preserving ACME challenge and app-site association flows. Files with a PHP-adjacent extension (`php`, `phtml`, `phar`, `inc`) are always blocked. All other paths fall through to the framework router as before.

This tightens the surface of newly scaffolded applications without changing existing routing behaviour for any request that the framework itself would normally handle.

### Secure configuration template and automatic entity-class sync
A `config/secure.php` template is now tracked in the repository and included in generated projects. The file provides a ready-to-use skeleton for the authentication configuration, including JWT audience, issuer, lifespan, and entity fields.

After a database is configured during project setup, `DatabaseInstaller` now calls `syncSecureAuthenticationDefaults()`, which rewrites the `entityClassName` value in `config/secure.php` to the correct fully-qualified class constant for the project's user resource. The constant is derived from the PSR-4 namespace declared in `composer.json` and the resource name entered during setup, so the generated configuration is accurate without manual editing.

The templates `.gitignore` has been updated to allow `config/secure.php` to be tracked, while continuing to exclude environment-specific overrides such as `config/secure.local.php`.

### Safer scaffolding output
Project names, paths, package names, and namespaces displayed in the scaffolding summary are now passed through `OutputFormatter::escape()` before being embedded in tagged output strings. Previously, a project name containing a `<` character could corrupt or suppress the formatted output block. The rendered output is otherwise unchanged.

### Improved module lookup in component scaffolding
The `getLocalModuleFilename()` method in the schematic module-management trait previously scanned only the immediate directory containing the generated file. If no module file was found there, the schematic would proceed without registering the new component in any module.

The method now walks up the directory tree from the target path to the `src/` root, stopping at the first directory that contains a `*Module.php` file. When multiple module files exist in the same directory, the one whose name matches the directory name is preferred. This means components generated into deep subdirectories are reliably registered in the nearest enclosing module rather than silently skipped.

The starter-page view template (`templates/src/Views/index.php`) has also been simplified: the PHP pre-processing block that assigned escaped variables at the top of the file has been removed, and the `htmlspecialchars()` calls are now written inline at each output point, which is the conventional pattern.

### Regression coverage
`0.8.4` adds and updates tests for all five areas:

* feature coverage confirms that the `serve` command constructs valid URIs and invocations across standard hostnames, IPv4, IPv6, and bracket-wrapped forms
* unit coverage confirms that the project summary renderer does not leak formatter tags when project metadata contains special characters
* feature coverage confirms that `config/secure.php` is written and updated with the correct entity class constant during a full project scaffold
* feature coverage confirms that component scaffolding correctly registers new components in the nearest enclosing module when the target path is nested
* feature coverage confirms that the generated front controller blocks PHP-adjacent extensions and passes `.well-known/` requests through

## What did not change

* No new commands were added.
* Existing command signatures are unchanged.
* MariaDB and MSSQL support introduced in `0.8.2` is unchanged.
* `--flat` and `--path` generation behaviour is unchanged.
* The framework release-line derivation introduced in `0.8.3` is unchanged.
* Existing projects do not need to be regenerated. The hardened front controller template applies only to newly scaffolded projects.

## Upgrade notes

If you are upgrading from `0.8.3`:

* No application code changes are required.
* New projects created with the CLI will receive the `config/secure.php` template and will have the `entityClassName` field populated automatically when a database is configured during setup.
* The `serve` command now rejects hosts that contain whitespace or shell-significant characters. Any automation that passes unusual host values to `assegai serve` should be reviewed.
* Newly generated front controllers use the hardened asset allowlist. Existing `index.php` files are not modified.

## What comes next

`0.9.0` remains focused on ORM stability:

* hardening MySQL support
* properly implementing SQLite as a supported target
* bringing PostgreSQL support up to the same level
* making the query builder and SQL generation properly dialect-aware

Full Changelog: [https://github.com/assegaiphp/console/compare/0.8.3...0.8.4](https://github.com/assegaiphp/console/compare/0.8.3...0.8.4)
