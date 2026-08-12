<?php

namespace iqual\Composer\ProjectScaffold\Tests\Unit;

use iqual\Composer\ProjectScaffold\Operations\OperationData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the normalization of operation metadata.
 */
#[CoversClass(OperationData::class)]
class OperationDataTest extends TestCase {

  /**
   * Tests that a string value is normalized to a path with the operation mode.
   */
  public function testStringValueBecomesPath() {
    $data = new OperationData('add', 'assets/add');
    $this->assertSame('add', $data->mode());
    $this->assertTrue($data->hasPath());
    $this->assertSame('assets/add', $data->path());
  }

  /**
   * Tests that the mode is derived from the operation key.
   */
  public function testModeIsDerivedFromOperation() {
    foreach (['add', 'replace', 'merge', 'read'] as $operation) {
      $data = new OperationData($operation, 'some/path');
      $this->assertSame($operation, $data->mode());
    }
  }

  /**
   * Tests that a value of FALSE is normalized to a skip operation.
   */
  public function testFalseValueBecomesSkip() {
    $data = new OperationData('add', FALSE);
    $this->assertSame('skip', $data->mode());
  }

  /**
   * Tests that a value of TRUE is rejected.
   */
  public function testTrueValueThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Asset location add cannot be given the value 'true'.");
    new OperationData('add', TRUE);
  }

  /**
   * Tests that an empty value is rejected.
   */
  public function testEmptyValueThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Asset location add cannot be empty.');
    new OperationData('add', '');
  }

  /**
   * Tests that an asset location named 'skip' with a path is rejected.
   */
  public function testSkipOperationNameWithPathThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Asset location skip cannot declare scaffold files. To skip an asset location, set its value to 'false' (e.g. \"add\": false).");
    new OperationData('skip', 'some/path');
  }

  /**
   * Tests that an asset location named 'skip' with an array is rejected.
   */
  public function testSkipOperationNameWithArrayThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Asset location skip cannot declare scaffold files. To skip an asset location, set its value to 'false' (e.g. \"add\": false).");
    new OperationData('skip', ['path' => 'some/path']);
  }

  /**
   * Tests that an asset location named 'skip' may still be set to false.
   */
  public function testSkipOperationNameWithFalseBecomesSkip() {
    $data = new OperationData('skip', FALSE);
    $this->assertSame('skip', $data->mode());
  }

}
