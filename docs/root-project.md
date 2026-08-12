# Controlling scaffolding in the root project

While [asset packages](./asset-package.md) define what gets scaffolded, the root project decides how and when. This page covers the options a root project can use to stay in control: ignoring individual files, disabling automatic scaffolding, forcing a hard re-scaffold and previewing changes with a dry run.

## Ignoring files

The root project can declare destination paths that must never be scaffolded, no matter which asset package provides them. This is useful when a project wants to maintain individual files (e.g. its `README.md`) by hand while still receiving all other managed assets:

```json
{
  "extra": {
    "project-scaffold": {
      "ignore-files": [
        "README.md",
        "web/*.txt",
        ".github/**"
      ]
    }
  }
}
```

Matched files are reported as skipped during scaffolding:

```
- Skipping [project-root]/README.md: ignored in root project configuration
```

Matching rules:

* `ignore-files` is only read from the root project's `composer.json`. An `ignore-files` entry in an asset package has no effect (asset packages can disable their own asset locations with `"<location>": false` instead).
* Patterns are matched against the destination path relative to the project root — the path of the file on disk, as also shown in the scaffolding log output. Templated sources are matched without their `.twig` suffix: to ignore a file scaffolded from `README.md.twig`, write `README.md`.
* Patterns use PHP's [`fnmatch()`](https://www.php.net/fnmatch) without `FNM_PATHNAME`: `*` and `?` also match directory separators. `web/*.txt` therefore matches `web/robots.txt` **and** nested paths such as `web/sub/notes.txt`, and `.github/**` matches everything below `.github/`.

## Disabling automatic scaffolding

Automatic scaffolding after `install` and `update` can be turned off; the explicit `project:*` commands keep working either way:

* Set `extra.project-scaffold.auto-scaffold` to `false` in the root `composer.json`.
* Or set the environment variable `PROJECT_SCAFFOLD_AUTO=0` (or `false`). The environment variable wins over the `composer.json` key, so `PROJECT_SCAFFOLD_AUTO=1` (or `true`) re-enables automatic scaffolding even when the key is set to `false`.

Note that `composer install --no-scripts` does **not** disable automatic scaffolding — the plugin subscribes to Composer events, which are not scripts. Only `--no-plugins`, or the opt-out above, prevents it.

## Forcing a hard re-scaffold

Both `project:scaffold` and `project:init` accept a `--force` flag that re-scaffolds a project from scratch: `add` assets overwrite files that already exist at their destination, and `merge` assets replace the destination with a fresh render of their source instead of merging values into it. `replace` assets always overwrite, with or without the flag.

> **Warning:** with `--force` any local modifications to scaffolded files are **lost** — including local edits to `add` files and values that were merged into `.env`, line-based, JSON or YAML files. Commit or back up local changes before running it.

## Previewing changes with a dry run

Both commands also accept a `--dry-run` flag that reports what each operation would do — every would-be action is marked with `(dry-run)` in the output — without changing anything: no files are created, overwritten, merged or deleted, and no variables or lock file hash are written to `composer.json` and `composer.lock`. Package questions are still evaluated (and, with `project:init`, asked) so the preview matches a real run, but the answers are not saved. Combine `--dry-run` with `--force` to preview a hard re-scaffold. Like Composer's own `--dry-run`, the `pre-`/`post-project-scaffold-cmd` scripts are not dispatched during a dry run.
