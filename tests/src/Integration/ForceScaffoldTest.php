<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests the forced (hard) re-scaffolding of existing files.
 */
class ForceScaffoldTest extends TestCase {

  /**
   * The fixtures helper.
   *
   * @var \iqual\Composer\ProjectScaffold\Tests\Fixtures
   */
  protected $fixtures;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fixtures = new Fixtures();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->fixtures->tearDown();
  }

  /**
   * Scaffolds the sut-preset fixture project and returns the SUT dir.
   */
  protected function scaffoldPresetProject() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    return $sut;
  }

  /**
   * Runs the scaffold operation again on an already scaffolded project.
   */
  protected function rescaffold(string $sut, bool $force = FALSE, bool $init = FALSE, array $user_inputs = []) {
    $io = $this->fixtures->io($user_inputs);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io, $init, $force);
    return $io;
  }

  /**
   * Tests that a forced run overwrites modified 'add' destinations.
   */
  public function testForceOverwritesExistingAddDestinations() {
    $sut = $this->scaffoldPresetProject();

    // Modify a file that was scaffolded by an 'add' operation and check
    // the file that already existed before the first scaffold run.
    file_put_contents($sut . '/settings.php', "<?php\n// Locally modified.\n");
    $this->assertStringContainsString('Original index.', file_get_contents($sut . '/app/web/index.php'));

    $io = $this->rescaffold($sut, force: TRUE);

    // Both 'add' destinations now contain the scaffold sources.
    $this->assertSame("<?php\n\n// Default settings scaffolded once, never overwritten.\n\$settings = [];\n", file_get_contents($sut . '/settings.php'));
    $this->assertStringContainsString('Hello from the scaffold.', file_get_contents($sut . '/app/web/index.php'));
    $this->assertStringNotContainsString('Original index.', file_get_contents($sut . '/app/web/index.php'));
    $this->assertStringContainsString('All assets have been scaffolded.', $io->getOutput());
  }

  /**
   * Tests that a forced run replaces 'merge' destinations with a fresh render.
   */
  public function testForceReplacesMergeDestinationsWithFreshRender() {
    $sut = $this->scaffoldPresetProject();

    // The first (regular) run merged the sources into the existing files,
    // keeping the local values.
    $this->assertSame("APP_NAME=preset-project\nDEBUG=true\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
    $this->assertSame("/vendor/\n*.log\n/node_modules/\n", file_get_contents($sut . '/.gitignore'));
    $this->assertStringContainsString('"name": "keep-me"', file_get_contents($sut . '/config.json'));
    $this->assertStringContainsString('restart: always', file_get_contents($sut . '/services.yml'));

    $this->rescaffold($sut, force: TRUE);

    // The forced run replaces the files with a fresh render of the
    // sources. The locally merged values are lost.
    $this->assertSame("APP_NAME=preset-project\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
    $this->assertSame("/vendor/\n/node_modules/\n", file_get_contents($sut . '/.gitignore'));
    $this->assertSame("{\n    \"database\": {\n        \"host\": \"db\",\n        \"port\": 3306\n    }\n}\n", file_get_contents($sut . '/config.json'));
    $this->assertSame("services:\n  app:\n    image: 'php:8.2'\n", file_get_contents($sut . '/services.yml'));
  }

  /**
   * Tests that without the force flag the behavior is unchanged.
   */
  public function testWithoutForceLocalModificationsAreKept() {
    $sut = $this->scaffoldPresetProject();

    file_put_contents($sut . '/settings.php', "<?php\n// Locally modified.\n");

    $io = $this->rescaffold($sut);
    $output = $io->getOutput();

    // The 'add' destination keeps the local modifications and the 'merge'
    // destinations keep the locally merged values.
    $this->assertSame("<?php\n// Locally modified.\n", file_get_contents($sut . '/settings.php'));
    $this->assertStringContainsString('because it already exists and overwrite is false', $output);
    $this->assertStringContainsString('Original index.', file_get_contents($sut . '/app/web/index.php'));
    $this->assertSame("APP_NAME=preset-project\nDEBUG=true\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
    $this->assertStringContainsString('"name": "keep-me"', file_get_contents($sut . '/config.json'));
  }

  /**
   * Tests that the init operation also supports a forced run.
   */
  public function testInitWithForceOverwritesExistingFiles() {
    $sut = $this->scaffoldPresetProject();

    file_put_contents($sut . '/settings.php', "<?php\n// Locally modified.\n");

    // Keep the preset name and PHP version, choose 'none' for deployment.
    $io = $this->rescaffold($sut, force: TRUE, init: TRUE, user_inputs: ['', '', '0']);

    $this->assertStringContainsString('Configure iqual/test-assets:', $io->getOutput());
    $this->assertSame("<?php\n\n// Default settings scaffolded once, never overwritten.\n\$settings = [];\n", file_get_contents($sut . '/settings.php'));
    $this->assertSame("APP_NAME=preset-project\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
  }

}
