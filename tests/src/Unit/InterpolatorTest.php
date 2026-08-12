<?php

namespace iqual\Composer\ProjectScaffold\Tests\Unit;

use iqual\Composer\ProjectScaffold\Util\Interpolator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Interpolator utility.
 */
#[CoversClass(Interpolator::class)]
class InterpolatorTest extends TestCase {

  /**
   * Tests token replacement with the default delimiters.
   */
  public function testInterpolate() {
    $interpolator = new Interpolator();
    $interpolator->setData(['web-root' => '/var/www', 'name' => 'example']);
    $this->assertSame('Root is /var/www for example.', $interpolator->interpolate('Root is [web-root] for [name].'));
  }

  /**
   * Tests that missing tokens are replaced with an empty string by default.
   */
  public function testMissingTokenIsRemoved() {
    $interpolator = new Interpolator();
    $this->assertSame('Value: ', $interpolator->interpolate('Value: [missing]'));
  }

  /**
   * Tests that missing tokens are kept when the default is FALSE.
   */
  public function testMissingTokenIsKeptWithFalseDefault() {
    $interpolator = new Interpolator();
    $this->assertSame('Value: [missing]', $interpolator->interpolate('Value: [missing]', [], FALSE));
  }

  /**
   * Tests that extra data overrides the configured data.
   */
  public function testExtraDataOverridesData() {
    $interpolator = new Interpolator();
    $interpolator->setData(['name' => 'original']);
    $this->assertSame('override', $interpolator->interpolate('[name]', ['name' => 'override']));
  }

  /**
   * Tests that addData() merges over previously set data.
   */
  public function testAddData() {
    $interpolator = new Interpolator();
    $interpolator->setData(['a' => '1', 'b' => '2']);
    $interpolator->addData(['b' => '3', 'c' => '4']);
    $this->assertSame('1-3-4', $interpolator->interpolate('[a]-[b]-[c]'));
  }

  /**
   * Tests custom token delimiters.
   */
  public function testCustomDelimiters() {
    $interpolator = new Interpolator('{{', '}}');
    $interpolator->setData(['name' => 'example']);
    $this->assertSame('example', $interpolator->interpolate('{{name}}'));
  }

  /**
   * Tests that a NULL message is treated as an empty string.
   */
  public function testNullMessage() {
    $interpolator = new Interpolator();
    $this->assertSame('', $interpolator->interpolate(NULL));
  }

  /**
   * Tests that list values are joined into a comma-separated string.
   */
  public function testListValuesAreJoined() {
    $interpolator = new Interpolator();
    $interpolator->setData([
      'languages' => ['de', 'fr'],
      'runtime' => ['php_version' => '8.3'],
    ]);
    $this->assertSame('Languages: de,fr', $interpolator->interpolate('Languages: [languages]'));
    // Maps cannot be represented in a string and fall back to the default.
    $this->assertSame('Runtime: ', $interpolator->interpolate('Runtime: [runtime]'));
  }

}
