<?php

namespace iqual\Composer\ProjectScaffold\Tests\Unit;

use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scaffold options value object.
 */
#[CoversClass(ScaffoldOptions::class)]
class ScaffoldOptionsTest extends TestCase {

  /**
   * Tests the detection of scaffold options in the 'extra' section.
   */
  public function testHasOptions() {
    $this->assertTrue(ScaffoldOptions::hasOptions(['project-scaffold' => []]));
    $this->assertFalse(ScaffoldOptions::hasOptions(['other' => []]));
    $this->assertFalse(ScaffoldOptions::hasOptions([]));
  }

  /**
   * Tests the default values of empty scaffold options.
   */
  public function testDefaults() {
    $options = ScaffoldOptions::create([], FALSE, 'test/package');
    $this->assertSame([], $options->allowedPackages());
    $this->assertFalse($options->hasAllowedPackages());
    $this->assertFalse($options->hasAssets());
    $this->assertSame([], $options->assets());
    $this->assertSame([
      'project-root' => '.',
      'app-root' => '.',
      'web-root' => 'web',
    ], $options->locations());
    $this->assertFalse($options->prompt());
    $this->assertFalse($options->getModified());
    $this->assertSame('test/package', $options->getPackageName());
  }

  /**
   * Tests that defined locations are merged over the default locations.
   */
  public function testLocations() {
    $options = ScaffoldOptions::create([
      'project-scaffold' => [
        'locations' => ['web-root' => 'app/web'],
      ],
    ], FALSE, 'test/package');
    $this->assertSame('app/web', $options->getLocation('web-root'));
    $this->assertSame('.', $options->getLocation('project-root'));
    $this->assertFalse($options->getLocation('missing'));
  }

  /**
   * Tests the modified state tracking of setOption().
   */
  public function testSetOptionTracksModified() {
    $options = ScaffoldOptions::create([
      'project-scaffold' => ['name' => 'example'],
    ], FALSE, 'test/package');
    $this->assertFalse($options->getModified());

    // Setting an option to its current value is not a modification.
    $options->setOption('name', 'example');
    $this->assertFalse($options->getModified());

    // Changing an existing option is a modification.
    $options->setOption('name', 'changed');
    $this->assertTrue($options->getModified());
    $this->assertSame('changed', $options->getOption('name'));
  }

  /**
   * Tests that setting a new option marks the options as modified.
   */
  public function testSetNewOptionTracksModified() {
    $options = ScaffoldOptions::create([], FALSE, 'test/package');
    $options->setOption('name', 'example');
    $this->assertTrue($options->getModified());
  }

  /**
   * Tests that only genuinely changed options are reported as modified.
   */
  public function testGetModifiedOptions() {
    $options = ScaffoldOptions::create([
      'project-scaffold' => ['name' => 'example'],
    ], FALSE, 'test/package');
    $this->assertSame([], $options->getModifiedOptions());

    // Setting an option to its current value is not a modification.
    $options->setOption('name', 'example');
    $this->assertSame([], $options->getModifiedOptions());

    // Changed and newly added options are reported with their current
    // values, untouched options (including the defaults) are not.
    $options->setOption('name', 'changed');
    $options->setOption('deployment', 'none');
    $this->assertSame([
      'name' => 'changed',
      'deployment' => 'none',
    ], $options->getModifiedOptions());
  }

  /**
   * Tests the ignore-files option accessor.
   */
  public function testIgnoreFiles() {
    // The option defaults to an empty list.
    $options = ScaffoldOptions::create([], FALSE, 'test/package');
    $this->assertSame([], $options->ignoreFiles());

    // Authored patterns are returned as-is.
    $options = ScaffoldOptions::create([
      'project-scaffold' => [
        'ignore-files' => ['README.md', 'web/*.txt'],
      ],
    ], FALSE, 'test/package');
    $this->assertSame(['README.md', 'web/*.txt'], $options->ignoreFiles());

    // A malformed (non-array) value is treated as no ignores.
    $options = ScaffoldOptions::create([
      'project-scaffold' => [
        'ignore-files' => 'README.md',
      ],
    ], FALSE, 'test/package');
    $this->assertSame([], $options->ignoreFiles());
  }

  /**
   * Tests that unknown options return NULL.
   */
  public function testGetMissingOptionReturnsNull() {
    $options = ScaffoldOptions::create([], FALSE, 'test/package');
    $this->assertNull($options->getOption('missing'));
  }

}
