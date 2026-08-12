# Agent notes

`iqual/project-scaffold` is a Composer plugin that scaffolds and updates
project files from versioned asset packages. It is inspired by
drupal/core-composer-scaffold and runs in production across hundreds of
client projects — regressions are expensive, be conservative.

## Ground rules

* `2.x` is the default branch and targets PHP `^8.3`. `1.x` is legacy
  (clients pinned to `^1.0`) and receives critical fixes only.
* The test suite pins current behavior. Never adjust a test expectation to
  make a code change pass without calling the behavior change out explicitly.
* The maintainer reviews and pushes everything himself: don't push, don't
  open PRs.
* Commit messages are short and past tense ("Fixed X", "Added Y").
* phpstan runs at level 1 without a baseline — keep it clean, don't
  introduce a new baseline.

## Orientation

* `src/Plugin.php` subscribes to composer events and lazily instantiates
  `src/Handler.php`, which orchestrates a scaffold run: resolve allowed
  packages, collect their assets, execute operations.
* `src/Operations/`: `add` (create once, never overwrite), `replace`
  (always overwrite, empty source deletes), `merge` (value-merge into
  `.env`/line-based/JSON/YAML files via `src/Util/ContentMerger.php`),
  `read` (questions definition, interactive prompts), `skip`.
* `*.twig` sources are rendered by `src/Util/Renderer.php`. Variables come
  from `extra.project-scaffold` of the asset packages and the root
  composer.json (root wins); answers are written back to the root
  composer.json.

## Tests

`make test` (ddev) or `composer test` (local PHP >= 8.3, no network
needed). Suites: `unit`, `integration` (in-process Handler with scripted
IO), `functional` (real composer against `tests/fixtures/`). Details in
`docs/testing.md`.
