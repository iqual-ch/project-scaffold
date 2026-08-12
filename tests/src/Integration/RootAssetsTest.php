<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\BufferIO;
use iqual\Composer\ProjectScaffold\Handler;
use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests scaffolding of root package assets and the options write-back.
 */
class RootAssetsTest extends TestCase {

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
   * Reads the scaffold options of a project's composer.json file.
   */
  protected function readScaffoldExtra(string $sut) {
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    return $definition['extra']['project-scaffold'];
  }

  /**
   * Runs the scaffold operation after a simulated `composer require`.
   *
   * Registers the given fixture package in the local repository, fires the
   * post-package-install event for it as `composer require` would, and then
   * runs the scaffold operation.
   */
  protected function scaffoldWithRequiredPackage(Composer $composer, BufferIO $io, string $package_name) {
    $handler = new Handler($composer, $io);
    $handler->addEventListeners('require');
    $package = $composer->getRepositoryManager()->getLocalRepository()->findPackage($package_name, '*');
    $operation = new InstallOperation($package);
    $event = new PackageEvent(PackageEvents::POST_PACKAGE_INSTALL, $composer, $io, FALSE, $composer->getRepositoryManager()->getLocalRepository(), [$operation], $operation);
    $handler->onPostPackageEvent($event);
    $handler->scaffold();
  }

  /**
   * Tests that root assets scaffold and override the package assets.
   */
  public function testRootAssetsScaffoldAndOverridePackages() {
    $sut = $this->fixtures->cloneFixtureProject('sut-root-assets');
    $composer_json = file_get_contents($sut . '/composer.json');
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    // The root package's own assets are scaffolded.
    $this->assertStringContainsString('Processing project scaffold for fixtures/sut-root-assets:', $output);

    // The root package wins over the asset package for the same destination.
    $this->assertSame("# Root README\n\nThis file is managed by the root package.\n", file_get_contents($sut . '/README.md'));
    $this->assertStringContainsString('overridden in fixtures/sut-root-assets', $output);

    // Assets of the allowed package without a collision still scaffold.
    $this->assertFileExists($sut . '/settings.php');

    // Nothing was modified, so the composer.json file is untouched.
    $this->assertSame($composer_json, file_get_contents($sut . '/composer.json'));
  }

  /**
   * Tests that requiring an already-allowed package writes nothing back.
   *
   * The run must not rewrite the `extra.project-scaffold` section: no
   * materialized default `locations`, no stripped `assets` key and no
   * root package name in its own `allowed-packages`.
   */
  public function testRequireOfAllowedPackageLeavesComposerJsonUntouched() {
    $sut = $this->fixtures->cloneFixtureProject('sut-root-assets');
    $composer_json = file_get_contents($sut . '/composer.json');
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->scaffoldWithRequiredPackage($composer, $io, 'iqual/test-assets');

    $this->assertStringContainsString('already allowed in the root-level composer.json file', $io->getOutput());
    $this->assertSame($composer_json, file_get_contents($sut . '/composer.json'));
  }

  /**
   * Tests that a newly required package is allowed without root last.
   *
   * The new package must be appended to the authored `allowed-packages`
   * list (never the root package's own name), all other authored keys of
   * the `extra.project-scaffold` section (e.g. `assets`) must be preserved
   * verbatim, and default values (e.g. `locations`) must not be
   * materialized into the composer.json file. At runtime the new package
   * must be inserted before the root package, so that the root package
   * still overrides all other packages.
   */
  public function testNewPackageIsAddedToAllowedPackagesBeforeRoot() {
    $sut = $this->fixtures->cloneFixtureProject('sut-root-assets');
    $io = $this->fixtures->io(['yes']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets-override', 'iqual/test-assets-override');
    $this->scaffoldWithRequiredPackage($composer, $io, 'iqual/test-assets-override');
    $output = $io->getOutput();

    $this->assertStringContainsString('add iqual/test-assets-override to the allowed packages', $output);

    // The root package still wins over the newly added package.
    $this->assertSame("# Root README\n\nThis file is managed by the root package.\n", file_get_contents($sut . '/README.md'));
    $this->assertStringContainsString('overridden in fixtures/sut-root-assets', $output);

    // The write-back only appends the new package to `allowed-packages`
    // and preserves the authored section as-is.
    $this->assertSame([
      'name' => 'root-assets-project',
      'runtime' => [
        'php_version' => '8.2',
      ],
      'deployment' => 'none',
      'allowed-packages' => [
        'iqual/test-assets',
        'iqual/test-assets-override',
      ],
      'assets' => [
        'replace' => 'root-assets/replace',
      ],
    ], $this->readScaffoldExtra($sut));
  }

}
