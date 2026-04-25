<div align="center">
    <a href="https://assegaiphp.com/" target="blank"><img src="https://assegaiphp.com/images/logos/logo-cropped.png" width="200" alt="Assegai Logo"></a>
</div>

<p align="center">
  <a href="https://github.com/assegaiphp/console/releases"><img alt="Latest release" src="https://img.shields.io/github/v/release/assegaiphp/console?display_name=tag&sort=semver&style=flat-square"></a>
  <a href="https://github.com/assegaiphp/console/actions/workflows/php.yml"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/assegaiphp/console/php.yml?branch=main&label=tests&style=flat-square"></a>
  <img alt="PHP 8.4+" src="https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php&logoColor=white">
  <a href="https://github.com/assegaiphp/console/blob/main/LICENSE"><img alt="License" src="https://img.shields.io/github/license/assegaiphp/console?style=flat-square"></a>
  <img alt="Status active" src="https://img.shields.io/badge/status-active-10b981?style=flat-square">
</p>

# Assegai Console

## Requirements
- PHP 8.4 (minimum)
- Composer 2.x.x

## Description

The Assegai Console is the framework CLI for:

- creating new projects
- serving apps locally
- generating framework features
- exporting API contracts and clients
- working with queues, migrations, databases, and Web Components
- upgrading existing workspaces across supported framework release lines

It also supports custom schematics so teams can teach `assegai generate` about their own company-specific features.

## Contribution workflow

For commit and pull request conventions in this repo, see:

- [docs/commit-and-pr-guidelines.md](./docs/commit-and-pr-guidelines.md)


## Installation

Install the Assegai Console globally using Composer:

```bash
$ composer global require assegaiphp/console
```

Then make sure Composer's global bin directory is on your `PATH`:

```bash
$ composer global config bin-dir --absolute
```

If the printed directory is not already on your `PATH`, add it in your shell profile. For example:

```bash
$ export PATH="$PATH:$(composer global config bin-dir --absolute)"
```

Refer to the [official Composer documentation](https://getcomposer.org/doc/00-intro.md) if your global Composer home is configured differently.

## Usage

### Get Started

To create a new Assegai project, run the following command:
```bash
$ assegai new my-app
```

This command will create a new Assegai project in the `my-app` directory.

The scaffold flow can also:

- initialize git
- configure a database
- write sensitive config to `config/secure.php`
- set up a starter users resource when ORM is enabled

### Development

After creating a new project, you can start the development server to preview your application in the browser.
```bash
$ cd my-app
```

To start the development server, navigate to the project directory and run the following command:
```bash
$ assegai serve
```

![Assegai Serve](assets/images/screenshots/serve.png)

### OpenSwoole runtime

If you want to try the long-lived runtime path instead of the default PHP development server, install the OpenSwoole extension first and then run:

```bash
$ assegai serve --runtime=openswoole
```

You can also persist that choice in `assegai.json`:

```json
{
  "development": {
    "server": {
      "runtime": "openswoole",
      "host": "127.0.0.1",
      "port": 9510,
      "openswoole": {
        "workerNum": 1,
        "taskWorkerNum": 0,
        "maxRequest": 0,
        "enableCoroutine": true,
        "hookFlags": "all"
      }
    }
  }
}
```

If the extension is not installed, the CLI now stops early with a direct setup message instead of falling into a runtime bootstrap failure.

The current OpenSwoole path is still experimental. It is intended for careful testing and advanced runtime work, not as a blanket replacement for the default `php` runtime in every project.

## Upgrading existing projects

Use the update command to move an existing workspace onto the current supported framework line:

```bash
$ assegai update
```

The CLI now upgrades installed first-party packages more deliberately and is aware of the active framework release line.

## Generating code

Use `assegai generate` (or `assegai g`) to scaffold framework artifacts:

```bash
$ assegai g resource users
$ assegai g component app --flat
$ assegai g page dashboard --path src/Admin
```

Useful options include:

- `--flat` to generate directly into the target path instead of creating a name-based subdirectory
- `--path` to place generated files at a source-relative path

Database-aware commands also support MySQL, MariaDB, PostgreSQL, SQLite, and MSSQL where applicable.

## Custom schematics

You can extend the generator without forking the CLI.

The default local convention is:

```text
schematics/<name>/
  schematic.json
  templates/
```

Start with a declarative starter:

```bash
assegai schematic:init loyalty-program
```

Or scaffold a PHP-backed starter when generation needs real logic:

```bash
assegai schematic:init customer-portal --php
```

Inspect what the CLI discovered:

```bash
assegai schematic:list
```

Run a custom schematic through the normal generate workflow:

```bash
assegai g loyalty-program rewards
```

For reusable team schematics, package manifests can be exposed through `composer.json`:

```json
{
  "extra": {
    "assegai": {
      "schematics": [
        "resources/loyalty/schematic.json"
      ]
    }
  }
}
```

Learn more in the [official documentation](https://assegaiphp.com/guide/getting-started/custom-cli-schematics).

## Stay in touch

* Author - [Andrew Masiye](https://twitter.com/feenix11), [Daniel Kaluba](https://twitter.com/ZombieKlassic)
* Website - [https://assegaiphp.com](https://assegaiphp.com/)
* X - [@assegaiphp](https://twitter.com/assegaiphp)

## License

Assegai Console is [MIT Licensed](LICENSE)

[schematics]: https://github.com/angular/angular-cli/tree/master/packages/angular_devkit/schematics
