<?php

namespace iqual\Composer\ProjectScaffold\Tests;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\Loader\ArrayLoader;
use Composer\Util\Filesystem;
use iqual\Composer\ProjectScaffold\Handler;
use iqual\Composer\ProjectScaffold\Options\RunOptions;
use iqual\Composer\ProjectScaffold\Util\Interpolator;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Convenience class for creating fixtures for the test suite.
 *
 * Heavily inspired by the fixtures of drupal/core-composer-scaffold's
 * test suite. Fixture projects are copied to a temporary directory and
 * their `*.tmpl` files are interpolated with the plugin's own Interpolator.
 * All package requirements are satisfied through `path` repositories
 * pointing at the working copy of the plugin and its installed vendor
 * packages, so no network access (packagist) is needed at all.
 */
class Fixtures {

  /**
   * Directories to delete when we are done.
   *
   * @var string[]
   */
  protected $tmpDirs = [];

  /**
   * The original working directory before a test changed it.
   *
   * @var string
   */
  protected $originalCwd;

  /**
   * Fixtures constructor.
   */
  public function __construct() {
    $this->originalCwd = getcwd();
  }

  /**
   * Gets the root directory of the plugin's working copy.
   *
   * @return string
   *   Path to the plugin's root directory.
   */
  public function pluginRoot() {
    return dirname(__DIR__, 2);
  }

  /**
   * Gets the directory holding the test fixtures.
   *
   * @return string
   *   Path to the fixtures directory.
   */
  public function fixturesDir() {
    return $this->pluginRoot() . '/tests/fixtures';
  }

  /**
   * Creates a new unique temporary directory.
   *
   * @param string $name
   *   Prefix for the temporary directory name.
   *
   * @return string
   *   Path to the temporary directory.
   */
  public function tmpDir(string $name) {
    $dir = sys_get_temp_dir() . '/project-scaffold-tests/' . $name . '-' . substr(md5(uniqid('', TRUE)), 0, 8);
    (new Filesystem())->ensureDirectoryExists($dir);
    $this->tmpDirs[] = $dir;
    return $dir;
  }

  /**
   * Cleans up all temporary directories and restores the working directory.
   */
  public function tearDown() {
    chdir($this->originalCwd);
    $fs = new Filesystem();
    foreach ($this->tmpDirs as $dir) {
      $fs->remove($dir);
    }
    $this->tmpDirs = [];
  }

  /**
   * Builds the list of path repositories used by the fixture projects.
   *
   * The plugin's working copy and the fixture asset packages are added
   * with fixed versions. Every package installed in the plugin's own
   * vendor directory is added as well, so that the plugin's requirements
   * (symfony/yaml, twig/twig, ...) can be resolved without packagist.
   *
   * @return array
   *   The repository definitions for a composer.json file.
   */
  public function pathRepositories() {
    $repositories = [
      [
        'type' => 'path',
        'url' => $this->pluginRoot(),
        'options' => [
          'versions' => ['iqual/project-scaffold' => '2.0.0'],
        ],
      ],
      [
        'type' => 'path',
        'url' => $this->fixturesDir() . '/test-assets',
        'options' => [
          'versions' => ['iqual/test-assets' => '1.0.0'],
        ],
      ],
      [
        'type' => 'path',
        'url' => $this->fixturesDir() . '/test-assets-override',
        'options' => [
          'versions' => ['iqual/test-assets-override' => '1.0.0'],
        ],
      ],
      [
        'type' => 'path',
        'url' => $this->fixturesDir() . '/test-assets-platform',
        'options' => [
          'versions' => ['iqual/test-assets-platform' => '1.0.0'],
        ],
      ],
      [
        'type' => 'path',
        'url' => $this->fixturesDir() . '/test-event-recorder',
        'options' => [
          'versions' => ['iqual/test-event-recorder' => '1.0.0'],
        ],
      ],
    ];
    $installed = json_decode(file_get_contents($this->pluginRoot() . '/vendor/composer/installed.json'), TRUE);
    foreach ($installed['packages'] as $package) {
      $repositories[] = [
        'type' => 'path',
        'url' => $this->pluginRoot() . '/vendor/' . $package['name'],
        'options' => [
          'versions' => [$package['name'] => $package['version']],
        ],
      ];
    }
    $repositories[] = ['packagist.org' => FALSE];
    return $repositories;
  }

  /**
   * Gets the data used to interpolate the `*.tmpl` fixture files.
   *
   * @return array
   *   Interpolation data.
   */
  public function interpolationData() {
    return [
      'plugin-root' => $this->pluginRoot(),
      'fixtures-dir' => $this->fixturesDir(),
      'path-repositories' => json_encode($this->pathRepositories(), JSON_UNESCAPED_SLASHES),
    ];
  }

  /**
   * Copies a fixture project to a temporary directory and interpolates it.
   *
   * All files ending in `.tmpl` are interpolated with the plugin's own
   * Interpolator and written to the same location without the suffix.
   *
   * @param string $name
   *   Name of the fixture project directory.
   *
   * @return string
   *   Path to the temporary copy of the fixture project.
   */
  public function cloneFixtureProject(string $name) {
    $sut = $this->tmpDir($name);
    $this->copyDir($this->fixturesDir() . '/' . $name, $sut);
    $interpolator = new Interpolator();
    $interpolator->setData($this->interpolationData());
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sut, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if (str_ends_with($file->getPathname(), '.tmpl')) {
        $contents = $interpolator->interpolate(file_get_contents($file->getPathname()));
        file_put_contents(substr($file->getPathname(), 0, -5), $contents);
        unlink($file->getPathname());
      }
    }
    return $sut;
  }

  /**
   * Creates a BufferIO object for capturing output and scripting input.
   *
   * @param array $user_inputs
   *   Scripted user input, one entry per prompt. If provided, the IO
   *   object becomes interactive.
   *
   * @return \Composer\IO\BufferIO
   *   The IO object.
   */
  public function io(array $user_inputs = []) {
    $io = new BufferIO('', OutputInterface::VERBOSITY_VERBOSE);
    if ($user_inputs) {
      $io->setUserInputs($user_inputs);
    }
    return $io;
  }

  /**
   * Creates a Composer object for a fixture project.
   *
   * Changes the working directory to the project, since the plugin
   * resolves the root package's paths relative to it.
   *
   * @param \Composer\IO\BufferIO $io
   *   The IO object to attach.
   * @param string $project_dir
   *   Path to the (cloned) fixture project.
   *
   * @return \Composer\Composer
   *   A fully functional Composer object.
   */
  public function createComposer(BufferIO $io, string $project_dir) {
    chdir($project_dir);
    return Factory::create($io, $project_dir . '/composer.json', TRUE);
  }

  /**
   * Simulates an installed fixture package in a fixture project.
   *
   * Copies the fixture package into the project's vendor directory and
   * registers it in the local repository of the Composer object, as if
   * `composer install` had installed it.
   *
   * @param \Composer\Composer $composer
   *   The Composer object of the fixture project.
   * @param string $project_dir
   *   Path to the fixture project.
   * @param string $fixture_name
   *   Name of the fixture package directory.
   * @param string $package_name
   *   The composer package name of the fixture package.
   * @param string $version
   *   The version to register the package with.
   */
  public function installFixturePackage(Composer $composer, string $project_dir, string $fixture_name, string $package_name, string $version = '1.0.0') {
    $target = $project_dir . '/vendor/' . $package_name;
    $this->copyDir($this->fixturesDir() . '/' . $fixture_name, $target);
    $definition = json_decode(file_get_contents($target . '/composer.json'), TRUE);
    $definition['version'] = $version;
    $package = (new ArrayLoader())->load($definition);
    $composer->getRepositoryManager()->getLocalRepository()->addPackage($package);
  }

  /**
   * Runs the scaffold operation of the plugin's Handler in-process.
   *
   * @param \Composer\Composer $composer
   *   The Composer object of the fixture project.
   * @param \Composer\IO\BufferIO $io
   *   The IO object to run with.
   * @param bool $init
   *   Whether to run the init (always prompt) operation instead.
   * @param bool $force
   *   Whether to force a hard re-scaffold of existing files.
   * @param bool $dry_run
   *   Whether to only report the operations without applying them.
   */
  public function runScaffold(Composer $composer, BufferIO $io, bool $init = FALSE, bool $force = FALSE, bool $dry_run = FALSE) {
    $handler = new Handler($composer, $io);
    $run_options = new RunOptions(force: $force, dry_run: $dry_run);
    if ($init) {
      $handler->init($run_options);
    }
    else {
      $handler->scaffold($run_options);
    }
  }

  /**
   * Recursively copies a directory.
   *
   * @param string $source
   *   Path to the source directory.
   * @param string $destination
   *   Path to the destination directory.
   */
  public function copyDir(string $source, string $destination) {
    $fs = new Filesystem();
    $fs->ensureDirectoryExists($destination);
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
      $target = $destination . '/' . $iterator->getSubPathname();
      if ($item->isDir()) {
        $fs->ensureDirectoryExists($target);
      }
      else {
        copy($item->getPathname(), $target);
      }
    }
  }

}
