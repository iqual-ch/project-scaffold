# Testing

The test suite pins the current behavior of the plugin. It consists of three suites:

* **unit** (`tests/src/Unit`): the utility and options classes in isolation, most notably the content merging (`.env`, line-based, JSON and YAML files) and the twig rendering.
* **integration** (`tests/src/Integration`): the scaffold operation running in-process against a real Composer object with a `BufferIO`, including the interactive questions logic with scripted user input.
* **functional** (`tests/src/Functional`): real `composer` commands (`install`, `update`, `create-project`, `project:*`) run with Symfony Process against fixture projects.

## Running the tests

Locally with [ddev](https://ddev.com) (`ddev start` once, then):

```bash
make install
make test              # all suites
make test-functional   # a single suite (test-unit, test-integration, test-functional)
make phpstan           # static analysis
```

Or directly with a local PHP >= 8.3: `composer install && composer test`.

## Fixtures

The fixture projects live in `tests/fixtures/`. `*.tmpl` files are interpolated with the plugin's own `Interpolator` when a fixture is cloned to a temporary directory; the `[path-repositories]` token expands to `path` repositories pointing at the plugin's working copy, the fixture asset packages and all packages in the plugin's own `vendor` directory. Packagist is disabled, so the whole suite runs offline. Composer's home and cache directories are isolated per test.

The `test-assets-platform` fixture package mirrors the shapes of the production platform asset packages (e.g. `iqual/drupal-platform`): non-string preset variables (floats, booleans, null), templated `add` assets, nested dot-directories, custom YAML tags with scalar values, the `op.new_file` merge context, conditionally-empty templates and question defaults interpolating earlier answers.

The `test-event-recorder` fixture plugin appends every `pre-install-cmd` and `pre-update-cmd` event it receives to `event-recorder.log` in the project root (the events plugins like cweagans/composer-patches hook into), so tests can assert that the plugin never runs a nested dependency update.

The functional tests use the `composer` binary found on the `PATH`; set the `COMPOSER_TEST_BINARY` environment variable to test against a different one.

## Manual simulation

To walk through the interactive questions yourself, `make simulate` clones a fixture project into `tmp/simulation/` (gitignored, inspectable from the host), installs it offline with the same path-repository setup the test suite uses (with `PROJECT_SCAFFOLD_AUTO=0`, so the install itself does not scaffold) and runs an interactive `composer project:init` in it:

```bash
make simulate          # fresh clone, install, interactive project:init
make simulate-rerun    # project:init again on the existing clone (round-trip check)
make simulate-clean    # remove tmp/simulation
```

The fixture project and preset variables can be overridden, e.g. `make simulate SIMULATION_FIXTURE=sut-questions SIMULATION_PRESETS="multilingual=yes deployment=none"`. The default presets enable the multiple choice languages question of the `test-assets` package. Composer's home and cache directories are isolated inside the simulation directory. If the prompts do not appear (no TTY), run the `composer project:init` step manually from a `ddev ssh` shell in `tmp/simulation`.

GitHub Actions (`.github/workflows/ci.yml`) runs static analysis (phpstan), the unit and integration suites on PHP 8.3/8.4/8.5 with lowest and highest dependencies, and the functional suite against the oldest supported, the latest stable and the snapshot Composer version.
