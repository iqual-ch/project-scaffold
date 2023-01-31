<?php

namespace iqual\Composer\ProjectScaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Composer\Console\Application;
use Composer\Command\ConfigCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use iqual\Composer\ProjectScaffold\Operations\OperationData;
use iqual\Composer\ProjectScaffold\Operations\OperationFactory;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFileCollection;
use iqual\Composer\ProjectScaffold\Options\ManageOptions;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;

/**
 * Core class of the plugin.
 *
 * Contains the primary logic which determines the files to be fetched and
 * processed.
 *
 * @internal
 */
class Handler {

  /**
   * Composer hook called before scaffolding begins.
   */
  const PRE_PROJECT_SCAFFOLD_CMD = 'pre-project-scaffold-cmd';

  /**
   * Composer hook called after scaffolding completes.
   */
  const POST_PROJECT_SCAFFOLD_CMD = 'post-project-scaffold-cmd';

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
   * The scaffold options in the top-level composer.json's 'extra' section.
   *
   * @var \iqual\Composer\ProjectScaffold\Options\ManageOptions
   */
  protected $manageOptions;

  /**
   * The manager that keeps track of which packages are allowed to scaffold.
   *
   * @var \iqual\Composer\ProjectScaffold\AllowedPackages
   */
  protected $manageAllowedPackages;

  /**
   * The list of listeners that are notified after a package event.
   *
   * @var \iqual\Composer\ProjectScaffold\PostPackageEventListenerInterface[]
   */
  protected $postPackageListeners = [];

  /**
   * Handler constructor.
   *
   * @param \Composer\Composer $composer
   *   The Composer service.
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service.
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->manageOptions = new ManageOptions($composer);
    $this->manageAllowedPackages = new AllowedPackages($composer, $io, $this->manageOptions);
  }

  /**
   * Register post-package events if 'require' or 'update' was called.
   */
  public function addEventListeners(string $command) {
    // In order to differentiate between post-package events called after
    // 'composer require' vs. the same events called at other times, we will
    // only install our handler when a 'require' event is detected.
    $this->postPackageListeners[] = [$this->manageAllowedPackages, $command];
  }

  /**
   * Posts package command event.
   *
   * We want to detect packages 'require'd that have scaffold files, but are not
   * yet allowed in the top-level composer.json file.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   */
  public function onPostPackageEvent(PackageEvent $event) {
    foreach ($this->postPackageListeners as $listener) {
      $listener[0]->event($event, $listener[1]);
    }
  }

  /**
   * Creates scaffold operation objects for all items in the file mappings.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package that relative paths will be relative from.
   * @param array $assets
   *   The package assets array keyed by destination path and the values
   *   are operation metadata arrays.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[]
   *   A list of scaffolding operation objects
   */
  protected function createScaffoldOperations(PackageInterface $package, array $assets) {
    $scaffold_op_factory = new OperationFactory($this->composer);
    $scaffold_ops = [];
    foreach ($assets as $operation => $data) {
      $operation_data = new OperationData($operation, $data);
      $scaffold_operations = $scaffold_op_factory->create($package, $operation_data);
      if (is_array($scaffold_operations)) {
        foreach ($scaffold_operations as $key => $scaffold_operation) {
          $scaffold_ops[$operation . ":" . $key] = $scaffold_operation;
        }
      }
      else {
        $scaffold_ops[$operation] = $scaffold_operations;
      }
    }
    return $scaffold_ops;
  }

  /**
   * Copies all scaffold files from source to destination.
   */
  public function scaffold(bool $override_prompt = FALSE) {
    // Recursively get the list of allowed packages. Only allowed packages
    // may declare scaffold files. Note that the top-level composer.json file
    // is implicitly allowed.
    $allowed_packages = $this->manageAllowedPackages->getAllowedPackages();
    $new_packages = $this->manageAllowedPackages->getNewPackages();
    $allowed_packages = $allowed_packages + $new_packages;
    if (empty($allowed_packages)) {
      $this->io->write("No projects scaffolded because no packages are available or allowed in the composer.json file.");
      return;
    }

    // Call any pre-scaffold scripts that may be defined.
    $dispatcher = new EventDispatcher($this->composer, $this->io);
    $dispatcher->dispatch(self::PRE_PROJECT_SCAFFOLD_CMD);

    // Fetch the list of file mappings from each allowed package and normalize
    // them.
    $assets = $this->getAssetsFromPackages($allowed_packages);

    $location_replacements = $this->manageOptions->getLocationReplacements();
    $scaffold_options = $this->manageOptions->getOptions();
    $modified_packages = $this->manageAllowedPackages->getModifiedPackages();

    // Add new packages to the `allowed-packages`.
    if (!empty($new_packages)) {
      $allowed_packages_array = array_keys((array) $allowed_packages);
      $scaffold_options->setOption("allowed-packages", $allowed_packages_array);
    }

    $scaffold_package_options = $this->getOptionsFromPackages($allowed_packages, $modified_packages, $override_prompt);

    // Create a collection of scaffolded files to process. This determines which
    // take priority and which are combined.
    $scaffold_files = new ScaffoldFileCollection($assets, $location_replacements);

    // Get the scaffold files whose contents on disk match what we are about to
    // write. These files are converted to a skip operation.
    $unchanged = $scaffold_files->checkUnchanged();
    $scaffold_files->filterFiles($unchanged, $location_replacements);

    // Process the list of scaffolded files.
    $scaffold_files->processScaffoldFiles($this->io, $scaffold_options, $scaffold_package_options);

    if ($scaffold_options->getModified() === TRUE) {
      // If the scaffolding options have been modified,
      // write them to the composer files.
      $this->writeOptionsToComposerFiles($scaffold_options);
    }
    else {
      // If the scaffolding options have not been modified
      // during the command execution, they might still have
      // been changed manually before execution.
      // Check the lock file then warn (and prompt).
      $locker = $this->composer->getLocker();
      if ($locker->isLocked() && !$locker->isFresh()) {
        $this->io->write('<warning>Lock file is not up to date with the latest changes in composer.json, it is recommended that you run `composer update --lock`.</warning>');
        if ($this->io->isInteractive()) {
          $update_lock_file = $this->io->askConfirmation("Would you like to <info>update the lock file</info> now? [<comment>yes</comment>] ");
          if ($update_lock_file === TRUE) {
            $this->updateComposerLock();
          }
        }
      }
    }

    // Call post-scaffold scripts.
    $dispatcher->dispatch(self::POST_PROJECT_SCAFFOLD_CMD);
  }

  /**
   * Copies all scaffold files from source to destination.
   */
  public function init() {
    $this->scaffold(TRUE);
  }

  /**
   * Gets the path to the 'vendor' directory.
   *
   * @return string
   *   The file path of the vendor directory.
   */
  protected function getVendorPath() {
    $vendor_dir = $this->composer->getConfig()->get('vendor-dir');
    $filesystem = new Filesystem();
    return $filesystem->normalizePath(realpath($vendor_dir));
  }

  /**
   * Gets a consolidated list of file mappings from all allowed packages.
   *
   * @param \Composer\Package\Package[] $allowed_packages
   *   A multidimensional array of file mappings, as returned by
   *   self::getAllowedPackages().
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[][]
   *   An array of destination paths => scaffold operation objects.
   */
  protected function getAssetsFromPackages(array $allowed_packages) {
    $assets = [];
    foreach ($allowed_packages as $package_name => $package) {
      $assets[$package_name] = $this->getPackageAssets($package);
    }
    return $assets;
  }

  /**
   * Gets a consolidated list of file mappings from all allowed packages.
   *
   * @param \Composer\Package\Package[] $allowed_packages
   *   A multidimensional array of file mappings, as returned by
   *   self::getAllowedPackages().
   * @param array $modified_packages
   *   Array of packages that have been modified and their modification type.
   * @param bool $override_prompt
   *   True if the user should always be prompted regargles of modifications.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[][]
   *   An array of destination paths => scaffold operation objects.
   */
  protected function getOptionsFromPackages(array $allowed_packages, array $modified_packages = [], bool $override_prompt = FALSE) {
    $options = [];
    $prompt = FALSE;
    // If there are modified packages prompt the user for input.
    if (!empty($modified_packages) || $override_prompt == TRUE) {
      $prompt = TRUE;
    }
    foreach ($allowed_packages as $package_name => $package) {
      $options[$package_name] = $this->manageOptions->packageOptions($package, $prompt);
    }
    return $options;
  }

  /**
   * Gets the array of file mappings provided by a given package.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The Composer package from which to get the file mappings.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\OperationInterface[]
   *   An array of destination paths => scaffold operation objects.
   */
  protected function getPackageAssets(PackageInterface $package) {
    $options = $this->manageOptions->packageOptions($package);
    if ($options->hasAssets()) {
      return $this->createScaffoldOperations($package, $options->assets());
    }
    // Warn if they allow a package that does not have any scaffold files.
    if (!$options->hasAllowedPackages()) {
      $this->io->writeError("The allowed package {$package->getName()} does not provide a assets for project scaffolding.");
    }
    return [];
  }

  /**
   * Gets the root package name.
   *
   * @return string
   *   The package name of the root project
   */
  protected function rootPackageName() {
    $root_package = $this->composer->getPackage();
    return $root_package->getName();
  }

  /**
   * Write the scaffold options to `composer.json` and update lock-file.
   *
   * @param \iqual\Composer\ProjectScaffold\Options\ScaffoldOptions $scaffold_options
   *   The scaffold options.
   */
  protected function writeOptionsToComposerFiles(ScaffoldOptions $scaffold_options) {
    $this->io->write("Writing variables to 'extra' section of top-level composer.json file.");

    // Update the composer.json file with the modified extra options.
    $scaffold_extras = $scaffold_options->getAllOptions();
    unset($scaffold_extras["assets"]);
    $input = new ArrayInput([
      'setting-key' => 'extra.project-scaffold',
      'setting-value' => [json_encode($scaffold_extras)],
      '--json' => TRUE,
    ]);
    $output = new ConsoleOutput();
    $command = new ConfigCommand();
    $command->setComposer($this->composer);
    $command->run($input, $output);

    $this->updateComposerLock();
  }

  /**
   * Update the `composer.lock`.
   */
  protected function updateComposerLock() {
    $this->io->write("Updating the hash in the composer.lock file.");

    // Update the composer.lock file with `update --lock`
    // Inspired by @see https://github.com/ergebnis/composer-normalize/blob/main/src/Command/NormalizeCommand.php#L513
    $input = new ArrayInput([
      'command' => 'update',
      '--lock' => TRUE,
      '--no-autoloader' => TRUE,
      '--no-install' => TRUE,
      '--no-plugins' => TRUE,
      '--no-scripts' => TRUE,
      '--interactive' => FALSE,
      '--ignore-platform-reqs' => TRUE,
    ]);
    $output = new ConsoleOutput();
    $application = new Application();
    $application->setAutoExit(FALSE);
    $application->run($input, $output);
  }

}
