<?php

namespace iqual\Composer\ProjectScaffold;

use Composer\Composer;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

use iqual\Composer\ProjectScaffold\Options\ManageOptions;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;

/**
 * Determine recursively which packages have been allowed to scaffold files.
 *
 * @internal
 */
class AllowedPackages implements PostPackageEventListenerInterface {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * Manager of the options in the top-level composer.json's 'extra' section.
   *
   * @var \iqual\Composer\ProjectScaffold\Options\ManageOptions
   */
  protected $manageOptions;

  /**
   * The list of new packages added by this Composer command.
   *
   * @var array
   */
  protected $newPackages = [];

  /**
   * The list of moidfied packages by this Composer command.
   *
   * @var array
   */
  protected $modifiedPackages = [];

  /**
   * AllowedPackages constructor.
   *
   * @param \Composer\Composer $composer
   *   The composer object.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to write to.
   * @param \iqual\Composer\ProjectScaffold\Options\ManageOptions $manage_options
   *   Manager of the options in the top-level composer.json's 'extra' section.
   */
  public function __construct(Composer $composer, IOInterface $io, ManageOptions $manage_options) {
    $this->composer = $composer;
    $this->io = $io;
    $this->manageOptions = $manage_options;
  }

  /**
   * Gets a list of all packages that are allowed to copy scaffold files.
   *
   * Projects must be explicitly whitelisted in the top-level composer.json
   * file in order to be allowed to override scaffold files.
   * Configuration for packages specified later will override configuration
   * specified by packages listed earlier. In other words, the last listed
   * package has the highest priority. The root package will always be returned
   * at the end of the list.
   *
   * @return \Composer\Package\PackageInterface[]
   *   An array of allowed Composer packages.
   */
  public function getAllowedPackages() {
    $top_level_packages = $this->getTopLevelAllowedPackages();
    $allowed_packages = $this->recursiveGetAllowedPackages($top_level_packages);
    // If the root package defines any assets, then implicitly add it
    // to the list of allowed packages. Add it at the end so that it overrides
    // all the preceding packages.
    if ($this->manageOptions->getOptions()->hasAssets()) {
      $root_package = $this->composer->getPackage();
      unset($allowed_packages[$root_package->getName()]);
      $allowed_packages[$root_package->getName()] = $root_package;
    }
    // Handle any newly-added packages that are not already allowed.
    return $this->evaluateNewPackages($allowed_packages);
  }

  /**
   * {@inheritdoc}
   */
  public function event(PackageEvent $event, string $command) {
    $operation = $event->getOperation();
    $package = $operation->getOperationType() === 'update' ? $operation->getTargetPackage() : $operation->getPackage();
    if ($command == "require" && ScaffoldOptions::hasOptions($package->getExtra())) {
      // Determine new packages that were required.
      // Later, in evaluateNewPackages(), we will report
      // which of the newly-installed packages have scaffold operations, and
      // whether or not they are allowed to scaffold by the allowed-packages
      // option in the root-level composer.json file.
      $this->newPackages[$package->getName()] = $package;
    }
    // Persist all modfied packages (install or update).
    $this->modifiedPackages[$package->getName()] = $operation->getOperationType();
  }

  /**
   * Gets all new packages that are being installed.
   *
   * @return array
   *   An array of allowed Composer package names.
   */
  public function getNewPackages() {
    return $this->newPackages;
  }

  /**
   * Gets all new packages that are being installed.
   *
   * @return array
   *   An array of allowed Composer package names.
   */
  public function getModifiedPackages() {
    return $this->modifiedPackages;
  }

  /**
   * Gets all packages that are allowed in the top-level composer.json.
   *
   * Currently no package is allowed per default. So any package
   * must be explicitly whitelisted in the top-level composer.json
   * file in order to be allowed to override scaffold files.
   *
   * @return array
   *   An array of allowed Composer package names.
   */
  protected function getTopLevelAllowedPackages() {
    $implicit_packages = [];
    $top_level_packages = $this->manageOptions->getOptions()->allowedPackages();
    return array_merge($implicit_packages, $top_level_packages);
  }

  /**
   * Builds a name-to-package mapping from a list of package names.
   *
   * @param string[] $packages_to_allow
   *   List of package names to allow.
   * @param array $allowed_packages
   *   Mapping of package names to PackageInterface of packages already
   *   accumulated.
   *
   * @return \Composer\Package\PackageInterface[]
   *   Mapping of package names to PackageInterface in priority order.
   */
  protected function recursiveGetAllowedPackages(array $packages_to_allow, array $allowed_packages = []) {
    foreach ($packages_to_allow as $name) {
      $package = $this->getPackage($name);
      if ($package instanceof PackageInterface && !isset($allowed_packages[$name])) {
        $allowed_packages[$name] = $package;
        $package_options = $this->manageOptions->packageOptions($package);
        $allowed_packages = $this->recursiveGetAllowedPackages($package_options->allowedPackages(), $allowed_packages);
      }
    }
    return $allowed_packages;
  }

  /**
   * Evaluates newly-added packages and see if they are already allowed.
   *
   * For now we will only emit warnings if they are not.
   *
   * @param array $allowed_packages
   *   Mapping of package names to PackageInterface of packages already
   *   accumulated.
   *
   * @return \Composer\Package\PackageInterface[]
   *   Mapping of package names to PackageInterface in priority order.
   */
  protected function evaluateNewPackages(array $allowed_packages) {
    foreach ($this->newPackages as $name => $newPackage) {
      if (!array_key_exists($name, $allowed_packages)) {
        $this->io->write("Not scaffolding files for <comment>{$name}</comment>, because it is not listed in the element 'extra.project-scaffold.allowed-packages' in the root-level composer.json file.");
        if ($this->io->isInteractive()) {
          $add_to_allowed_packages = $this->io->askConfirmation("Would you like to <info>add {$name} to the allowed packages</info> now? [<comment>yes</comment>] ");
          if ($add_to_allowed_packages !== TRUE) {
            unset($this->newPackages[$name]);
          }
        }
        else {
          unset($this->newPackages[$name]);
        }
      }
      else {
        $this->io->write("Package <comment>{$name}</comment> has scaffold operations, and is already allowed in the root-level composer.json file.");
      }
    }
    return $allowed_packages;
  }

  /**
   * Retrieves a package from the current composer process.
   *
   * @param string $name
   *   Name of the package to get from the current composer installation.
   *
   * @return \Composer\Package\PackageInterface|null
   *   The Composer package.
   */
  protected function getPackage($name) {
    return $this->composer->getRepositoryManager()->getLocalRepository()->findPackage($name, '*');
  }

}
