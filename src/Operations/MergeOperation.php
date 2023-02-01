<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldResult;
use iqual\Composer\ProjectScaffold\Util\Renderer;
use iqual\Composer\ProjectScaffold\Util\ContentMerger;

/**
 * Scaffold operation to copy or symlink from source to destination.
 *
 * @internal
 */
class MergeOperation extends AbstractOperation {

  /**
   * Identifies Merge operations.
   */
  const ID = 'merge';

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
   * The contents from the file that we are merging into.
   *
   * @var string
   */
  protected $originalContents;

  /**
   * Constructs a MergeOperation.
   *
   * @param \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath $sourcePath
   *   The relative path to the source file.
   * @param bool $overwrite
   *   Whether to allow this scaffold file to overwrite files already at
   *   the destination. Defaults to TRUE.
   */
  public function __construct(ScaffoldFilePath $sourcePath, $overwrite = TRUE) {
    $this->source = $sourcePath;
    $this->overwrite = $overwrite;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateContents(array $variables = []) {
    $merge_contents = "";
    $source_path = $this->source->relativePath();
    $new_file = FALSE;

    // Check if the destination/original has contents.
    $original_contents = $this->originalContents;
    if (empty($original_contents)) {
      $new_file = TRUE;
    }

    // Get contents of source file.
    if ($this->isTemplated() === TRUE) {
      $render_variables = $variables + [
        "op" => [
          "id" => $this::ID,
          "new_file" => $new_file,
        ],
      ];
      $renderer = new Renderer($render_variables);
      $merge_contents = $renderer->render($this->source->fullPath());
    }
    else {
      $merge_contents = file_get_contents($this->source->fullPath());
    }

    // If it's a new file set contents.
    if ($new_file) {
      $contents = $merge_contents;
    }
    // If the destination has contents, do a merge.
    else {
      $merge_type = ContentMerger::detectMergeType($source_path);
      $contents = ContentMerger::mergeContents($original_contents, $merge_contents, $merge_type);
    }

    return $contents;
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
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options, ScaffoldOptions $package_options) {
    $fs = new Filesystem();
    $destination_path = $destination->fullPath();

    // Do nothing if overwrite is 'false' and a file already exists at the
    // destination.
    $interpolator = $destination->getInterpolator();
    if ($this->overwrite === FALSE && file_exists($destination_path)) {
      $io->write($interpolator->interpolate("  - Skipping <info>[dest-full-path]</info> because it already exists and overwrite is <comment>false</comment>."), TRUE, 4);
      return new ScaffoldResult($destination, FALSE);
    }

    // Get the root scaffold options.
    $scaffold_options = $options->getAllOptions();
    // Get the package scaffold options.
    $scaffold_package_options = $package_options->getAllOptions();

    // Resolve the package's scaffold options and the root options.
    $scaffold_variables = array_replace_recursive($scaffold_package_options, $scaffold_options);

    // Generate new contents.
    $new_contents = $this->contents($scaffold_variables);

    // Skip unchanged files. Files that are unchanged and
    // are not templated are skipped earlier during
    // the creation of the ScaffoldFileCollection.
    if ($this->originalContents === $new_contents) {
      $io->write($interpolator->interpolate("  - Skipping <info>[dest-full-path]</info> because files are identical."), TRUE, 4);
      return new ScaffoldResult($destination, FALSE);
    }

    // Make sure the directory exists.
    $fs->ensureDirectoryExists(dirname($destination_path));
    // Copy the new contents to the destination.
    return $this->copyScaffold($destination, $io, $new_contents);
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

    $io->write($interpolator->interpolate("  - Writing <info>[dest-full-path]</info> (Merge)"));
    return new ScaffoldResult($destination, $this->overwrite);
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
    $existingData = NULL;
    if (file_exists($destination->fullPath())) {
      $existingData = file_get_contents($destination->fullPath());
    }

    // Cache the original data to use during merge.
    $this->originalContents = $existingData;

    return $this;
  }

}
