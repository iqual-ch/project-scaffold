<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scaffold operation in-process with a real Composer object.
 */
class ScaffoldTest extends TestCase {

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
   * Scaffolds the sut-preset fixture project and returns the SUT dir and IO.
   */
  protected function scaffoldPresetProject(array $extra_packages = []) {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    if ($extra_packages) {
      $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
      $definition['extra']['project-scaffold']['allowed-packages'] = array_merge($definition['extra']['project-scaffold']['allowed-packages'], array_keys($extra_packages));
      file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    foreach ($extra_packages as $package_name => $fixture_name) {
      $this->fixtures->installFixturePackage($composer, $sut, $fixture_name, $package_name);
    }
    $this->fixtures->runScaffold($composer, $io);
    return [$sut, $io];
  }

  /**
   * Tests a non-interactive scaffold run with all variables preset.
   */
  public function testScaffoldWithPresetVariables() {
    [$sut, $io] = $this->scaffoldPresetProject();
    $output = $io->getOutput();

    $this->assertStringContainsString('Initializing iqual/test-assets:', $output);
    $this->assertStringContainsString('All required variables found.', $output);
    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets:', $output);
    $this->assertStringContainsString('All assets have been scaffolded.', $output);

    // The 'add' operation does not overwrite the existing file at the
    // interpolated [web-root] location, but creates the missing one.
    $this->assertStringContainsString('Original index.', file_get_contents($sut . '/app/web/index.php'));
    $this->assertFileExists($sut . '/settings.php');

    // The 'replace' operation renders twig templates with the merged
    // package and root variables, and copies static files verbatim.
    $this->assertSame("# preset-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
    $this->assertSame("all:\n\t@echo \"managed by scaffold\"\n", file_get_contents($sut . '/Makefile'));

    // A 'replace' asset with empty contents deletes the destination.
    $this->assertFileDoesNotExist($sut . '/delete-me.txt');

    // The 'merge' operation updates existing values and appends new ones.
    $this->assertSame("APP_NAME=preset-project\nDEBUG=true\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
    $this->assertSame("/vendor/\n*.log\n/node_modules/\n", file_get_contents($sut . '/.gitignore'));

    // JSON merges keep unknown keys and the original indentation.
    $expected_json = <<<JSON
{
  "name": "keep-me",
  "database": {
    "host": "db",
    "port": 3306
  }
}
JSON;
    $this->assertSame($expected_json, file_get_contents($sut . '/config.json'));

    // YAML merges preserve comments and unmanaged keys.
    $services = file_get_contents($sut . '/services.yml');
    $this->assertStringContainsString('# Service definitions for the project.', $services);
    $this->assertStringContainsString('# The container image to use.', $services);
    $this->assertStringContainsString("image: 'php:8.2'", $services);
    $this->assertStringContainsString('restart: always', $services);
  }

  /**
   * Tests that a second scaffold run does not change any files.
   */
  public function testScaffoldIsIdempotent() {
    [$sut, $io] = $this->scaffoldPresetProject();

    $contents = [];
    foreach (['README.md', 'Makefile', '.env', '.gitignore', 'config.json', 'services.yml', 'settings.php'] as $file) {
      $contents[$file] = file_get_contents($sut . '/' . $file);
    }

    $second_io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($second_io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $second_io);
    $output = $second_io->getOutput();

    $this->assertStringContainsString('because files are identical', $output);
    foreach ($contents as $file => $original) {
      $this->assertSame($original, file_get_contents($sut . '/' . $file), "File {$file} changed on the second scaffold run.");
    }
  }

  /**
   * Tests that a later allowed package overrides files of an earlier one.
   */
  public function testPackageOverridesEarlierPackage() {
    [$sut, $io] = $this->scaffoldPresetProject(['iqual/test-assets-override' => 'test-assets-override']);
    $output = $io->getOutput();

    $this->assertSame("# preset-project (overridden)\n\nThis README is provided by the override package.\n", file_get_contents($sut . '/README.md'));
    $this->assertStringContainsString('overridden in iqual/test-assets-override', $output);
  }

  /**
   * Tests that the scaffold command hooks reach the composer script listeners.
   */
  public function testScaffoldCommandHooksAreDispatched() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['scripts'] = [
      'pre-project-scaffold-cmd' => 'iqual\\Composer\\ProjectScaffold\\Tests\\Integration\\ScaffoldHooks::hook',
      'post-project-scaffold-cmd' => 'iqual\\Composer\\ProjectScaffold\\Tests\\Integration\\ScaffoldHooks::hook',
    ];
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    $this->assertStringContainsString('pre-project-scaffold-cmd hook for fixtures/sut-preset', $output);
    $this->assertStringContainsString('post-project-scaffold-cmd hook for fixtures/sut-preset', $output);
  }

  /**
   * Tests that scaffolding into a read-only directory restores its permissions.
   */
  public function testScaffoldIntoReadOnlyDirectory() {
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
      $this->markTestSkipped('Directory permissions are not enforced when running as root.');
    }
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['allowed-packages'][] = 'iqual/test-assets-readonly';
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    mkdir($sut . '/config');
    chmod($sut . '/config', 0555);

    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets-readonly', 'iqual/test-assets-readonly');
    $this->fixtures->runScaffold($composer, $io);

    // Restore write permissions before asserting, so that the temporary
    // directory can be cleaned up even when an assertion fails.
    $directory_mode = fileperms($sut . '/config') & 0777;
    chmod($sut . '/config', 0755);

    $this->assertSame(0555, $directory_mode);
    $this->assertSame("managed: true\n", file_get_contents($sut . '/config/managed.yml'));
    $this->assertSame("{\n    \"managed\": true\n}\n", file_get_contents($sut . '/config/app.json'));
  }

  /**
   * Tests that an empty source deletes from a read-only directory.
   */
  public function testDeleteFromReadOnlyDirectory() {
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
      $this->markTestSkipped('Directory permissions are not enforced when running as root.');
    }
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['allowed-packages'][] = 'iqual/test-assets-readonly';
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    mkdir($sut . '/config');
    file_put_contents($sut . '/config/obsolete.txt', "obsolete\n");
    chmod($sut . '/config', 0555);

    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets-readonly', 'iqual/test-assets-readonly');
    $this->fixtures->runScaffold($composer, $io);

    // Restore write permissions before asserting, so that the temporary
    // directory can be cleaned up even when an assertion fails.
    $directory_mode = fileperms($sut . '/config') & 0777;
    chmod($sut . '/config', 0755);

    $this->assertSame(0555, $directory_mode);
    $this->assertFileDoesNotExist($sut . '/config/obsolete.txt');
    $this->assertStringContainsString('Deleting', $io->getOutput());
    $this->assertStringContainsString('obsolete.txt', $io->getOutput());
  }

  /**
   * Tests that a replace asset with empty contents deletes the destination.
   */
  public function testReplaceWithEmptySourceDeletesDestination() {
    [$sut, $io] = $this->scaffoldPresetProject();

    $this->assertFileDoesNotExist($sut . '/delete-me.txt');
    $this->assertStringContainsString('Deleting', $io->getOutput());
    $this->assertStringContainsString('delete-me.txt', $io->getOutput());

    // A second run skips the operation because the destination is gone.
    $second_io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($second_io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $second_io);

    $this->assertFileDoesNotExist($sut . '/delete-me.txt');
    $this->assertStringContainsString('because source is empty and destination inexistent', $second_io->getOutput());
  }

  /**
   * Tests that nothing is scaffolded without allowed packages.
   */
  public function testScaffoldWithoutAllowedPackages() {
    $sut = $this->fixtures->cloneFixtureProject('sut-empty');
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->runScaffold($composer, $io);

    $this->assertStringContainsString('No projects scaffolded because no packages are available or allowed', $io->getOutput());
  }

}
