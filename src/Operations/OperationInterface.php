<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use Composer\IO\IOInterface;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;

/**
 * Interface for scaffold operation objects.
 *
 * @internal
 */
interface OperationInterface {

  /**
   * Returns the exact data that will be written to the scaffold files.
   *
   * @return string
   *   Data to be written to the scaffold location.
   */
  public function contents();

  /**
   * Returns if the scaffold file needs to be templated.
   *
   * @return bool
   *   If the operation is using temlpates.
   */
  public function isTemplated();

  /**
   * Process this scaffold operation.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $destination
   *   Scaffold file's destination path.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to write to.
   * @param \iqual\Composer\ProjectScaffold\Options\ScaffoldOptions $options
   *   Various options that may alter the behavior of the operation.
   * @param \iqual\Composer\ProjectScaffold\Options\ScaffoldOptions $package_options
   *   Package options that may alter the behavior of the operation.
   *
   * @return \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldResult
   *   Result of the scaffolding operation.
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options, ScaffoldOptions $package_options);

  /**
   * Determines what to do if operation is used at same path as a previous op.
   *
   * Default behavior is to scaffold this operation at the specified
   * destination, ignoring whatever was there before.
   *
   * @param OperationInterface $existing_target
   *   Existing file at the destination path that we should combine with.
   *
   * @return OperationInterface
   *   The op to use at this destination.
   */
  public function scaffoldOverExistingTarget(OperationInterface $existing_target);

  /**
   * Determines what to do if operation is used without a previous operation.
   *
   * Default behavior is to scaffold this operation at the specified
   * destination. Most operations overwrite rather than modify existing files,
   * and therefore do not need to do anything special when there is no existing
   * file.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $destination
   *   Scaffold file's destination path.
   *
   * @return OperationInterface
   *   The op to use at this destination.
   */
  public function scaffoldAtNewLocation(ScaffoldFilePath $destination);

}
