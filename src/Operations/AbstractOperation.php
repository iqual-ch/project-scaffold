<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;

/**
 * Provides default behaviors for operations.
 *
 * @internal
 */
abstract class AbstractOperation implements OperationInterface {

  /**
   * Cached contents of scaffold file to be written to disk.
   *
   * @var string
   */
  protected $contents;

  /**
   * {@inheritdoc}
   */
  final public function contents(array $variables = []) {
    if (!isset($this->contents)) {
      $this->contents = $this->generateContents($variables);
    }
    return $this->contents;
  }

  /**
   * Load the scaffold contents or otherwise generate what is needed.
   *
   * @param array $variables
   *   The variables available to templated scaffold files.
   *
   * @return string
   *   The contents of the scaffold file.
   */
  abstract protected function generateContents(array $variables = []);

  /**
   * {@inheritdoc}
   */
  public function scaffoldOverExistingTarget(OperationInterface $existing_target) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function scaffoldAtNewLocation(ScaffoldFilePath $destination) {
    return $this;
  }

}
