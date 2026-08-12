<?php

/**
 * @file
 * Prepares a fixture project for a manual, interactive scaffold run.
 *
 * Clones a fixture project into tmp/simulation (instead of the
 * auto-removed temporary directory the test suite uses), so that a real
 * composer can be run against it and the result can be inspected from
 * the host. See the `simulate` target in the Makefile.
 *
 * Usage: php tests/bin/simulate-prepare.php [fixture-name] [key=value ...]
 *
 * Any key=value arguments are preset as scaffold variables in the
 * `extra.project-scaffold` section of the cloned composer.json file.
 */

use Composer\Util\Filesystem;
use iqual\Composer\ProjectScaffold\Tests\Fixtures;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$arguments = array_slice($argv, 1);
$fixture = 'sut-questions';
if (isset($arguments[0]) && !str_contains($arguments[0], '=')) {
  $fixture = array_shift($arguments);
}

// Clone the fixture project into a stable directory inside the
// repository, wiping any previous simulation first.
$target = dirname(__DIR__, 2) . '/tmp/simulation';
(new Filesystem())->remove($target);

$fixtures = new class($target) extends Fixtures {

  public function __construct(protected string $target) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function tmpDir(string $name) {
    (new Filesystem())->ensureDirectoryExists($this->target);
    return $this->target;
  }

};
$sut = $fixtures->cloneFixtureProject($fixture);

// Preset scaffold variables passed as key=value arguments.
if ($arguments) {
  $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
  foreach ($arguments as $argument) {
    [$key, $value] = explode('=', $argument, 2);
    $definition['extra']['project-scaffold'][$key] = $value;
  }
  file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

echo "Prepared the fixture project '{$fixture}' in {$sut}\n";
