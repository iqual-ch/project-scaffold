<?php

namespace iqual\Composer\ProjectScaffold\Operations;

/**
 * Holds parameter data for operation objects during operation creation only.
 *
 * @internal
 */
class OperationData {

  const MODE = 'mode';
  const PATH = 'path';
  const ADD = 'add';
  const REPLACE = 'replace';
  const MERGE = 'merge';
  const READ = 'read';

  /**
   * The parameter data.
   *
   * @var array
   */
  protected $data;

  /**
   * The operation path.
   *
   * @var string
   */
  protected $operation;

  /**
   * OperationData constructor.
   *
   * @param string $operation
   *   The operation path.
   * @param mixed $data
   *   The raw data array to wrap.
   */
  public function __construct($operation, $data) {
    $this->operation = $operation;
    $this->data = $this->normalizeScaffoldMetadata($operation, $data);
  }

  /**
   * Gets the operation path that this operation data is associated with.
   *
   * @return string
   *   The operation path for the scaffold result.
   */
  public function operation() {
    return $this->operation;
  }

  /**
   * Gets operation mode.
   *
   * @return string
   *   Operation mode.
   */
  public function mode() {
    return $this->data[self::MODE];
  }

  /**
   * Checks if path exists.
   *
   * @return bool
   *   Returns true if path exists
   */
  public function hasPath() {
    return isset($this->data[self::PATH]);
  }

  /**
   * Gets path.
   *
   * @return string
   *   The path.
   */
  public function path() {
    return $this->data[self::PATH];
  }

  /**
   * Normalizes metadata by converting literal values into arrays.
   *
   * Conversions performed include:
   *   - Boolean 'false' means "skip".
   *   - A string means "replace", with the string value becoming the path.
   *
   * @param string $operation
   *   The operation for the scaffold file.
   * @param mixed $value
   *   The metadata for this operation object, which varies by operation type.
   *
   * @return array
   *   Normalized scaffold metadata with default values.
   */
  protected function normalizeScaffoldMetadata($operation, $value) {
    $defaultScaffoldMetadata = [
      self::MODE => AddOperation::ID,
    ];

    return $this->convertScaffoldMetadata($operation, $value) + $defaultScaffoldMetadata;
  }

  /**
   * Performs the conversion-to-array step in normalizeScaffoldMetadata.
   *
   * @param string $operation
   *   The operation path for the scaffold file.
   * @param mixed $value
   *   The metadata for this operation object, which varies by operation type.
   *
   * @return array
   *   Normalized scaffold metadata.
   */
  protected function convertScaffoldMetadata($operation, $value) {
    if (is_bool($value)) {
      if (!$value) {
        return [self::MODE => SkipOperation::ID];
      }
      throw new \RuntimeException("Asset location {$operation} cannot be given the value 'true'.");
    }
    if (empty($value)) {
      throw new \RuntimeException("Asset location {$operation} cannot be empty.");
    }
    if ($operation == SkipOperation::ID) {
      throw new \RuntimeException("Asset location {$operation} cannot declare scaffold files. To skip an asset location, set its value to 'false' (e.g. \"add\": false).");
    }
    if (is_string($value)) {
      $value = [self::PATH => $value];
    }

    if ($operation == self::ADD || $operation == self::REPLACE || $operation == self::MERGE ||$operation == self::READ) {
      $value[self::MODE] = $operation;
    }

    return $value;
  }

}
