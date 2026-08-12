# Project Scaffold

A Composer plugin that scaffolds and updates project files from versioned asset packages.

Files that usually get copied from project to project by hand — CI workflows, `.ddev` or `.devcontainer` setups, `.env` templates, `.gitignore`, Makefiles — can instead be defined once in an asset package and managed by Composer. When the package is required, updated or used with `composer create-project`, the plugin places the files, renders templates and, if run interactively, asks the questions the package defines and saves the answers to the project's `composer.json`.

The plugin is heavily inspired by Drupal's [drupal/core-composer-scaffold](https://github.com/drupal/core-composer-scaffold), generalized beyond Drupal. Credit to its contributors.

## How it works

An asset package declares its files under `extra.project-scaffold.assets`, grouped by operation:

* `add` — created once, never overwritten.
* `replace` — kept in sync on every update; an empty source deletes the destination.
* `merge` — values are merged into existing `.env`, line-based (`.gitignore`), JSON and YAML files, preserving local additions and comments.
* `read` — a questions definition; missing or newly asked variables are prompted interactively and written back to the root `composer.json`.

Sources ending in `.twig` are rendered with the project's variables before being placed. See [creating a project asset package](./docs/asset-package.md) for the full format.

The root project stays in control: destination paths listed under `extra.project-scaffold.ignore-files` (glob patterns allowed) are never scaffolded, no matter which package provides them.

## Getting started

```bash
composer require iqual/project-scaffold --dev
```

Allow the plugin when Composer asks, then require one or more asset packages. The plugin prompts for the package's questions, saves the answers under `extra.project-scaffold` and scaffolds the files. From then on, `composer update` keeps the managed files up to date.

## Commands

| Command | Purpose |
| --- | --- |
| `composer project:init` | Prompt all package questions again and re-apply the scaffolding. |
| `composer project:update` | Alias of `project:init`. |
| `composer project:scaffold` | Re-apply the scaffolding without prompting. |

Scaffolding also runs automatically after `composer install`, `update`, `require` and `create-project`.

### Forcing a hard re-scaffold

Both `project:scaffold` and `project:init` accept a `--force` flag that re-scaffolds a project from scratch: `add` assets overwrite files that already exist at their destination, and `merge` assets replace the destination with a fresh render of their source instead of merging values into it. `replace` assets always overwrite, with or without the flag.

> **Warning:** with `--force` any local modifications to scaffolded files are **lost** — including local edits to `add` files and values that were merged into `.env`, line-based, JSON or YAML files. Commit or back up local changes before running it.

### Previewing changes with a dry run

Both commands also accept a `--dry-run` flag that reports what each operation would do — every would-be action is marked with `(dry-run)` in the output — without changing anything: no files are created, overwritten, merged or deleted, and no variables or lock file hash are written to `composer.json` and `composer.lock`. Package questions are still evaluated (and, with `project:init`, asked) so the preview matches a real run, but the answers are not saved. Combine `--dry-run` with `--force` to preview a hard re-scaffold. Like Composer's own `--dry-run`, the `pre-`/`post-project-scaffold-cmd` scripts are not dispatched during a dry run.

### Disabling automatic scaffolding

Automatic scaffolding after `install` and `update` can be turned off; the explicit `project:*` commands keep working either way:

* Set `extra.project-scaffold.auto-scaffold` to `false` in the root `composer.json`.
* Or set the environment variable `PROJECT_SCAFFOLD_AUTO=0` (or `false`). The environment variable wins over the `composer.json` key, so `PROJECT_SCAFFOLD_AUTO=1` (or `true`) re-enables automatic scaffolding even when the key is set to `false`.

Note that `composer install --no-scripts` does **not** disable automatic scaffolding — the plugin subscribes to Composer events, which are not scripts. Only `--no-plugins`, or the opt-out above, prevents it.

## Versions

| Branch | Requirements | Status |
| --- | --- | --- |
| `2.x` | PHP `^8.3`, Composer 2.6+ | Active development |
| `1.x` | PHP >= 7.3 | Critical fixes only |

## Development

The test suite runs offline against fixture projects, locally via [ddev](https://ddev.com) (`ddev start`, then `make install` and `make test`) or any PHP >= 8.3 (`composer test`). See [testing](./docs/testing.md).
