<?php

namespace iqual\Composer\ProjectScaffold\ScaffoldFiles;

use Composer\IO\IOInterface;
use iqual\Composer\ProjectScaffold\Util\Interpolator;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;
use iqual\Composer\ProjectScaffold\Operations\SkipOperation;

/**
 * Collection of scaffold files.
 *
 * @internal
 */
class ScaffoldFileCollection implements \IteratorAggregate {

  /**
   * Nested list of all scaffold files.
   *
   * The top level array maps from the package name to the collection of
   * scaffold files provided by that package. Each collection of scaffold files
   * is keyed by destination path.
   *
   * @var \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFileInfo[][]
   */
  protected $scaffoldFilesByProject = [];

  /**
   * ScaffoldFileCollection constructor.
   *
   * @param \iqual\Composer\ProjectScaffold\Operations\OperationInterface[][] $file_mappings
   *   A multidimensional array of file mappings.
   * @param \iqual\Composer\ProjectScaffold\Interpolator $location_replacements
   *   An object with the location mappings (e.g. [web-root]).
   */
  public function __construct(array $file_mappings, Interpolator $location_replacements) {
    // Collection of all destination paths to be scaffolded. Used to determine
    // when two projects scaffold the same file and we have to either replace or
    // combine them together.
    // @see OperationInterface::scaffoldOverExistingTarget().
    $scaffoldFiles = [];

    // Build the list of ScaffoldFileInfo objects by project.
    foreach ($file_mappings as $package_name => $package_file_mappings) {
      // Make sure that "read" operations come first,
      // if the asset exists.
      if (array_key_exists("read", $package_file_mappings)) {
        $package_file_mappings = array_replace(array_flip(['read']), $package_file_mappings);
      }

      foreach ($package_file_mappings as $type => $op) {
        if ($type == "read") {
          $destination = ScaffoldFilePath::destinationPath($package_name, NULL, $location_replacements);
          $scaffold_file = new ScaffoldFileInfo($destination, $op);
          $this->scaffoldFilesByProject[$package_name][$type] = $scaffold_file;
          continue;
        }

        $destination_rel_path = $op->getRelativeSourcePath();

        $destination_rel_path = str_replace("@web-root", "[web-root]", $destination_rel_path);
        $destination_rel_path = str_replace("@app-root", "[app-root]", $destination_rel_path);
        $destination_rel_path = str_replace("@project-root", "[project-root]", $destination_rel_path);

        if (str_contains($destination_rel_path, "[") === FALSE) {
          $destination_rel_path = "[project-root]/" . $destination_rel_path;
        }

        $destination = ScaffoldFilePath::destinationPath($package_name, $destination_rel_path, $location_replacements);

        // If there was already a scaffolding operation happening at this path,
        // allow the new operation to decide how to handle the override.
        // Usually, the new operation will replace whatever was there before.
        if (isset($scaffoldFiles[$destination_rel_path])) {
          $previous_scaffold_file = $scaffoldFiles[$destination_rel_path];
          $op = $op->scaffoldOverExistingTarget($previous_scaffold_file->op());

          // Remove the previous op so we only touch the destination once.
          $message = "  - Skipping <info>[dest-rel-path]</info>: overridden in <comment>{$package_name}</comment>";
          $this->scaffoldFilesByProject[$previous_scaffold_file->packageName()][$destination_rel_path] = new ScaffoldFileInfo($destination, new SkipOperation($message));
        }
        // If there is NOT already a scaffolding operation happening at this
        // path, notify the scaffold operation of this fact.
        else {
          $op = $op->scaffoldAtNewLocation($destination);
        }

        // Combine the scaffold operation with the destination and record it.
        $scaffold_file = new ScaffoldFileInfo($destination, $op);
        $scaffoldFiles[$destination_rel_path] = $scaffold_file;
        $this->scaffoldFilesByProject[$package_name][$destination_rel_path] = $scaffold_file;
      }
    }
  }

  /**
   * Removes any item that has a path matching any path in the provided list.
   *
   * Matching is done via destination path.
   *
   * @param string[] $files_to_filter
   *   List of destination paths.
   * @param \iqual\Composer\ProjectScaffold\Interpolator $location_replacements
   *   An object with the location mappings (e.g. [web-root]).
   */
  public function filterFiles(array $files_to_filter, Interpolator $location_replacements) {
    foreach ($this->scaffoldFilesByProject as $project_name => $scaffold_files) {
      foreach ($scaffold_files as $destination_rel_path => $scaffold_file) {
        if (in_array($destination_rel_path, $files_to_filter, TRUE)) {
          // Convert the previous operation to a skip operation.
          $destination = ScaffoldFilePath::destinationPath($project_name, $destination_rel_path, $location_replacements);
          $message = "  - Skipping <info>[dest-full-path]</info> because files are identical.";
          $scaffold_files[$destination_rel_path] = new ScaffoldFileInfo($destination, new SkipOperation($message));
        }
      }
      $this->scaffoldFilesByProject[$project_name] = $scaffold_files;
    }
  }

  /**
   * {@inheritdoc}
   */
  #[\ReturnTypeWillChange]
  public function getIterator() {
    return new \ArrayIterator($this->scaffoldFilesByProject);
  }

  /**
   * Processes the files in our collection.
   *
   * @param \Composer\IO\IOInterface $io
   *   The Composer IO object.
   * @param \iqual\Composer\ProjectScaffold\Options\ScaffoldOptions $scaffold_options
   *   The scaffold options.
   * @param array $package_options
   *   The array of package scaffold options.
   *
   * @return \iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldResult[]
   *   The results array.
   */
  public function processScaffoldFiles(IOInterface $io, ScaffoldOptions $scaffold_options, array $package_options) {
    $results = [];
    foreach ($this as $project_name => $scaffold_files) {
      $process_message = TRUE;

      // If a read asset exists, it will come first.
      if (array_key_exists("read", $scaffold_files)) {
        $io->write("Initializing <comment>{$project_name}</comment>:", TRUE, 4);
      }

      // Process the scaffold files.
      foreach ($scaffold_files as $scaffold_key => $scaffold_file) {
        if ($process_message === TRUE && $scaffold_key != "read") {
          $io->write("Processing project scaffold for <comment>{$project_name}</comment>:");
          $process_message = FALSE;
        }
        $results[$scaffold_file->destination()->relativePath()] = $scaffold_file->process($io, $scaffold_options, $package_options[$project_name]);
      }
      $io->write("All assets have been scaffolded.");
    }
    return $results;
  }

  /**
   * Returns the list of files that have not changed since they were scaffolded.
   *
   * @return string[]
   *   List of relative paths to unchanged files on disk.
   */
  public function checkUnchanged() {
    $results = [];
    foreach ($this as $scaffold_files) {
      foreach ($scaffold_files as $op => $scaffold_file) {
        // Ignore "read" operations.
        if ($op != "read") {
          // Check if files have changed but ignore templated files,
          // since their variables are not yet available.
          if ($scaffold_file->op()->isTemplated() === FALSE && !$scaffold_file->hasChanged()) {
            $results[] = $scaffold_file->destination()->relativePath();
          }
        }
      }
    }
    return $results;
  }

}
