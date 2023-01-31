<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use Composer\Composer;
use Composer\Package\PackageInterface;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;

/**
 * Create Scaffold operation objects based on provided metadata.
 *
 * @internal
 */
class OperationFactory {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * OperationFactory constructor.
   *
   * @param \Composer\Composer $composer
   *   Reference to the 'Composer' object, since the Scaffold Operation Factory
   *   is also responsible for evaluating relative package paths as it creates
   *   scaffold operations.
   */
  public function __construct(Composer $composer) {
    $this->composer = $composer;
  }

  /**
   * Creates a scaffolding operation object as determined by the metadata.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param OperationData $operation_data
   *   The parameter data for this operation object; varies by operation type.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface
   *   The scaffolding operation object (skip, replace, etc.)
   *
   * @throws \RuntimeException
   *   Exception thrown when parameter data does not identify a known scaffold
   *   operation.
   */
  public function create(PackageInterface $package, OperationData $operation_data) {
    switch ($operation_data->mode()) {
      case SkipOperation::ID:
        return new SkipOperation();

      case AddOperation::ID:
        return $this->createAddOperation($package, $operation_data);

      case AddOperation::ID_OVERWRITE:
        return $this->createReplaceOperation($package, $operation_data);

      case MergeOperation::ID:
        return $this->createMergeOperation($package, $operation_data);

      case ReadOperation::ID:
        return $this->createReadOperation($package, $operation_data);
    }
    throw new \RuntimeException("Unknown scaffold operation mode <comment>{$operation_data->mode()}</comment>.");
  }

  /**
   * Creates an 'add' scaffold op.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param OperationData $operation_data
   *   The parameter data for this operation object, i.e. the relative 'path'.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[]
   *   A list of scaffold replace operation objects.
   */
  protected function createAddOperation(PackageInterface $package, OperationData $operation_data) {
    $package_name = $package->getName();
    $package_path = $this->getPackagePath($package);
    $paths = ScaffoldFilePath::sourcePaths($package_name, $package_path, $operation_data->path());
    $operations = [];
    foreach ($paths as $path) {
      $operations[] = new addOperation($path, FALSE);
    }
    return $operations;
  }

  /**
   * Creates an 'replace' scaffold op.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param OperationData $operation_data
   *   The parameter data for this operation object, i.e. the relative 'path'.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[]
   *   A list of scaffold replace operation objects.
   */
  protected function createReplaceOperation(PackageInterface $package, OperationData $operation_data) {
    $package_name = $package->getName();
    $package_path = $this->getPackagePath($package);
    $paths = ScaffoldFilePath::sourcePaths($package_name, $package_path, $operation_data->path());
    $operations = [];
    foreach ($paths as $path) {
      $operations[] = new addOperation($path, TRUE);
    }
    return $operations;
  }

  /**
   * Creates an 'merge' scaffold op.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param OperationData $operation_data
   *   The parameter data for this operation object, i.e. the relative 'path'.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[]
   *   A list of scaffold replace operation objects.
   */
  protected function createMergeOperation(PackageInterface $package, OperationData $operation_data) {
    $package_name = $package->getName();
    $package_path = $this->getPackagePath($package);
    $paths = ScaffoldFilePath::sourcePaths($package_name, $package_path, $operation_data->path());
    $operations = [];
    foreach ($paths as $path) {
      $operations[] = new mergeOperation($path, TRUE);
    }
    return $operations;
  }

  /**
   * Creates an 'read' scaffold op.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param OperationData $operation_data
   *   The parameter data for this operation object, i.e. the relative 'path'.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[]
   *   A list of scaffold read operation objects.
   */
  protected function createReadOperation(PackageInterface $package, OperationData $operation_data) {
    if (!$operation_data->hasPath()) {
      throw new \RuntimeException("'path' component required for 'read' operations.");
    }
    $package_name = $package->getName();
    $package_path = $this->getPackagePath($package);
    $source = ScaffoldFilePath::sourcePath($package_name, $package_path, NULL, $operation_data->path());
    $op = new ReadOperation($source);
    return $op;
  }

  /**
   * Gets the file path of a package.
   *
   * Note that if we call getInstallPath on the root package, we get the
   * wrong answer (the installation manager thinks our package is in
   * vendor). We therefore add special checking for this case.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package.
   *
   * @return string
   *   The file path.
   */
  protected function getPackagePath(PackageInterface $package) {
    if ($package->getName() == $this->composer->getPackage()->getName()) {
      // This will respect the --working-dir option if Composer is invoked with
      // it. There is no API or method to determine the filesystem path of
      // a package's composer.json file.
      return getcwd();
    }
    return $this->composer->getInstallationManager()->getInstallPath($package);
  }

}
