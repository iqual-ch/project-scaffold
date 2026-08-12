<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use iqual\Composer\ProjectScaffold\Options\RunOptions;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldResult;
use iqual\Composer\ProjectScaffold\Util\Renderer;

/**
 * Scaffold operation to copy or symlink from source to destination.
 *
 * @internal
 */
class AddOperation extends AbstractOperation {

  /**
   * Identifies Add operations.
   */
  const ID = 'add';

  /**
   * Identifies Replace operations.
   */
  const ID_OVERWRITE = 'replace';

  /**
   * The relative path to the source file.
   *
   * @var \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath
   */
  public $source;

  /**
   * Whether to overwrite existing files.
   *
   * @var bool
   */
  protected $overwrite;

  /**
   * The contents from the file that we writing to.
   *
   * @var string
   */
  protected $originalContents;

  /**
   * Constructs a AddOperation.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $sourcePath
   *   The relative path to the source file.
   * @param bool $overwrite
   *   Whether to allow this scaffold file to overwrite files already at
   *   the destination. Defaults to TRUE.
   */
  public function __construct(ScaffoldFilePath $sourcePath, $overwrite = FALSE) {
    $this->source = $sourcePath;
    $this->overwrite = $overwrite;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateContents(array $variables = []) {
    if ($this->isTemplated() === TRUE) {
      $renderer = new Renderer($variables);
      return $renderer->render($this->source->fullPath());
    }
    else {
      return file_get_contents($this->source->fullPath());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRelativeSourcePath() {
    return $this->source->relativePath();
  }

  /**
   * {@inheritdoc}
   */
  public function isTemplated() {
    if (str_ends_with($this->source->relativePath(), ".twig") !== FALSE) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options, ScaffoldOptions $package_options, RunOptions $run_options) {
    $fs = new Filesystem();
    $destination_path = $destination->fullPath();

    // Do nothing if overwrite is 'false' and a file already exists at the
    // destination. A forced run overwrites the existing file anyhow,
    // discarding any local modifications.
    $interpolator = $destination->getInterpolator();
    if ($this->overwrite === FALSE && $run_options->force() === FALSE && file_exists($destination_path)) {
      $io->write($interpolator->interpolate("  - Skipping <info>[dest-full-path]</info> because it already exists and overwrite is <comment>false</comment>."), TRUE, 4);
      return new ScaffoldResult($destination, FALSE);
    }

    // Get the root scaffold options.
    $scaffold_options = $options->getAllOptions();
    // Get the package scaffold options.
    $scaffold_package_options = $package_options->getAllOptions();

    // Resolve the package's scaffold options and the root options.
    $scaffold_variables = ScaffoldOptions::mergeVariables($scaffold_package_options, $scaffold_options);

    // Generate new contents.
    $new_contents = $this->contents($scaffold_variables);

    // Skip unchanged files.
    // Files that are not templated are skipped earlier during
    // the creation of the ScaffoldFileCollection.
    if ($this->originalContents === $new_contents) {
      $io->write($interpolator->interpolate("  - Skipping <info>[dest-full-path]</info> because files are identical."), TRUE, 4);
      return new ScaffoldResult($destination, FALSE);
    }

    // If the "add" source is empty, delete the destination file.
    if (empty($new_contents)) {
      // @todo maybe change mechanism and add DeleteOperation
      if (file_exists($destination_path)) {
        // A dry-run only reports the deletion instead of performing it.
        if ($run_options->dryRun() === TRUE) {
          $io->write($interpolator->interpolate("  - Deleting <info>[dest-full-path]</info>. (dry-run)"));
          return new ScaffoldResult($destination, FALSE);
        }
        try {
          // Delete the file at the destination.
          return $this->deleteScaffold($destination, $io);
        }
        catch (\Exception $e) {
          $io->write($interpolator->interpolate("  - Could not delete <info>[dest-full-path]</info>: <warning>" . $e->getMessage() . "</warning>"));
        }
      }
      else {
        $io->write($interpolator->interpolate("  - Skipping <info>[dest-full-path]</info> because source is empty and destination inexistent."), TRUE, 4);
      }
      return new ScaffoldResult($destination, FALSE);
    }
    // If there is content try to write the content.
    else {
      // A dry-run only reports the write instead of performing it.
      if ($run_options->dryRun() === TRUE) {
        $operation_type = $this->overwrite ? "Replace" : "Add";
        $io->write($interpolator->interpolate("  - Writing <info>[dest-full-path]</info> ($operation_type) (dry-run)"));
        return new ScaffoldResult($destination, $this->overwrite);
      }
      try {
        // Make sure the directory exists.
        $fs->ensureDirectoryExists(dirname($destination_path));
        // Copy the new contents to the destination.
        return $this->copyScaffold($destination, $io, $new_contents);
      }
      catch (\Exception $e) {
        $io->write($interpolator->interpolate("  - Could not write <info>[dest-full-path]</info>: <warning>" . $e->getMessage() . "</warning>"));
        return new ScaffoldResult($destination, FALSE);
      }
    }
  }

  /**
   * Copies the scaffold file.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $destination
   *   Scaffold file to process.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to writing to.
   * @param string $new_contents
   *   The new contents being scaffolded.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\ScaffoldResult
   *   The scaffold result.
   */
  protected function copyScaffold(ScaffoldFilePath $destination, IOInterface $io, string $new_contents) {
    $interpolator = $destination->getInterpolator();
    $this->source->addInterpolationData($interpolator);
    $destination_directory = dirname($destination->fullPath());

    // Make sure the destination directory is writable, if not change
    // permissions temporarily.
    $directory_mode = $this->makeDestinationDirectoryWritable($destination, $io);

    try {
      // Make sure the file is writable, if not change permissions temporarily.
      $file_mode = NULL;
      if (file_exists($destination->fullPath()) && !is_writable($destination->fullPath())) {
        $file_mode = (stat($destination->fullPath()))['mode'] & 000777;
        $io->write($interpolator->interpolate("  - Changing <info>[dest-full-path]</info> file permissions temporarily."), TRUE, 4);
        chmod($destination->fullPath(), 0644);
      }

      // Write the contents.
      if (file_put_contents($destination->fullPath(), $new_contents) === FALSE) {
        throw new \RuntimeException($interpolator->interpolate("Could not copy source file <info>[src-rel-path]</info> to <info>[dest-rel-path]</info>!"));
      }

      // Revert possible previous permissions changes.
      if ($file_mode !== NULL) {
        chmod($destination->fullPath(), $file_mode);
      }
    }
    finally {
      // Revert possible directory permissions changes.
      if ($directory_mode !== NULL) {
        @chmod($destination_directory, $directory_mode);
      }
    }

    $operation_type = $this->overwrite ? "Replace" : "Add";
    $io->write($interpolator->interpolate("  - Writing <info>[dest-full-path]</info> ($operation_type)"));
    return new ScaffoldResult($destination, $this->overwrite);
  }

  /**
   * Deletes the file at the destination.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $destination
   *   Scaffold file to process.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to writing to.
   *
   * @return \iqual\Composer\ProjectScaffold\Operations\ScaffoldResult
   *   The scaffold result.
   */
  protected function deleteScaffold(ScaffoldFilePath $destination, IOInterface $io) {
    $fs = new Filesystem();
    $interpolator = $destination->getInterpolator();
    $destination_directory = dirname($destination->fullPath());

    // Make sure the destination directory is writable, if not change
    // permissions temporarily.
    $directory_mode = $this->makeDestinationDirectoryWritable($destination, $io);

    try {
      // Delete the file at the destination.
      $fs->remove($destination->fullPath());
    }
    finally {
      // Revert possible directory permissions changes.
      if ($directory_mode !== NULL) {
        @chmod($destination_directory, $directory_mode);
      }
    }

    $io->write($interpolator->interpolate("  - Deleting <info>[dest-full-path]</info>."));
    return new ScaffoldResult($destination, FALSE);
  }

  /**
   * Makes the destination directory writable if it is not.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $destination
   *   Scaffold file to process.
   * @param \Composer\IO\IOInterface $io
   *   IOInterface to writing to.
   *
   * @return int|null
   *   The original directory permissions if they were changed, NULL otherwise.
   */
  protected function makeDestinationDirectoryWritable(ScaffoldFilePath $destination, IOInterface $io) {
    $interpolator = $destination->getInterpolator();
    $destination_directory = dirname($destination->fullPath());

    $directory_mode = NULL;
    if (!is_writable($destination_directory)) {
      $directory_mode = fileperms($destination_directory) & 000777;
      $io->write($interpolator->interpolate("  - Changing <info>[dest-full-path]</info> directory permissions temporarily."), TRUE, 4);
      if (!@chmod($destination_directory, $directory_mode | 0200)) {
        throw new \RuntimeException($interpolator->interpolate("Could not make the directory of <info>[dest-rel-path]</info> writable!"));
      }
    }
    return $directory_mode;
  }

  /**
   * {@inheritdoc}
   */
  public function scaffoldOverExistingTarget(OperationInterface $existing_target) {
    $this->originalContents = $existing_target->contents();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function scaffoldAtNewLocation(ScaffoldFilePath $destination) {
    // If the target file DOES exist, and it already contains the append/prepend
    // data, then we will skip the operation.
    $existingData = NULL;
    if (file_exists($destination->fullPath())) {
      $existingData = file_get_contents($destination->fullPath());
    }

    // Cache the original data to use during add.
    $this->originalContents = $existingData;

    return $this;
  }

}
