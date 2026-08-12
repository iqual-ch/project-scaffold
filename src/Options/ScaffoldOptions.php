<?php

namespace iqual\Composer\ProjectScaffold\Options;

/**
 * Per-project options from the 'extras' section of the composer.json file.
 *
 * Projects that describe scaffold files do so via their scaffold options. This
 * data is pulled from the 'project-scaffold' portion of the extras section of
 * the project data.
 *
 * @internal
 */
class ScaffoldOptions {

  /**
   * The name of the package.
   *
   * @var string
   */
  protected $packageName = "";

  /**
   * The raw data from the 'extras' section of the top-level composer.json file.
   *
   * @var array
   */
  protected $options = [];

  /**
   * States if the 'extras' options have been modified.
   *
   * @var bool
   */
  protected $modified = FALSE;

  /**
   * The names of the options that have been modified.
   *
   * @var array
   */
  protected $modifiedOptions = [];

  /**
   * States if the user should be prompted for setting options.
   *
   * @var bool
   */
  protected $prompt;

  /**
   * ScaffoldOptions constructor.
   *
   * @param array $options
   *   The scaffold options taken from the 'project-scaffold' section.
   * @param bool $prompt
   *   If the user should be prompted for this package.
   * @param string $package_name
   *   The name of the package.
   */
  protected function __construct(array $options, bool $prompt, string $package_name) {
    $this->packageName = $package_name;

    $this->options = $options + [
      "allowed-packages" => [],
      "locations" => [],
      "assets" => [],
      "ignore-files" => [],
    ];

    // Default locations.
    $this->options['locations'] += [
      'project-root' => '.',
      'app-root' => '.',
      'web-root' => 'web',
    ];

    $this->prompt = $prompt;
  }

  /**
   * Determines if the provided 'extras' section has scaffold options.
   *
   * @param array $extras
   *   The contents of the 'extras' section.
   *
   * @return bool
   *   True if scaffold options have been declared
   */
  public static function hasOptions(array $extras) {
    return array_key_exists('project-scaffold', $extras);
  }

  /**
   * Creates a scaffold options object.
   *
   * @param array $extras
   *   The contents of the 'extras' section.
   * @param bool $prompt
   *   If the user should be prompted for this package.
   * @param string $package_name
   *   The name of the package.
   *
   * @return self
   *   The scaffold options object representing the provided scaffold options
   */
  public static function create(array $extras, bool $prompt, string $package_name) {
    $options = static::hasOptions($extras) ? $extras['project-scaffold'] : [];
    return new self($options, $prompt, $package_name);
  }

  /**
   * Determines whether any allowed packages were defined.
   *
   * @return bool
   *   Whether there are allowed packages
   */
  public function hasAllowedPackages() {
    return !empty($this->allowedPackages());
  }

  /**
   * Gets allowed packages from these options.
   *
   * @return array
   *   The list of allowed packages
   */
  public function allowedPackages() {
    return $this->options['allowed-packages'];
  }

  /**
   * Gets the location mapping table, e.g. 'webroot' => './'.
   *
   * @return array
   *   A map of name : location values
   */
  public function locations() {
    return $this->options['locations'];
  }

  /**
   * Gets the value if users should be prompted.
   *
   * @return bool
   *   True if the user should be prompted.
   */
  public function prompt() {
    return $this->prompt;
  }

  /**
   * Determines whether a given named location is defined.
   *
   * @param string $name
   *   The location name to search for.
   *
   * @return bool
   *   True if the specified named location exist.
   */
  protected function hasLocation($name) {
    return array_key_exists($name, $this->locations());
  }

  /**
   * Gets a specific named location.
   *
   * @param string $name
   *   The name of the location to fetch.
   *
   * @return string
   *   The value of the provided named location
   */
  public function getLocation($name) {
    return $this->hasLocation($name) ? $this->locations()[$name] : FALSE;
  }

  /**
   * Determines if there are assets.
   *
   * @return bool
   *   Whether or not the scaffold options contain any file mappings
   */
  public function hasAssets() {
    return !empty($this->assets());
  }

  /**
   * Returns the actual assets.
   *
   * @return array
   *   File mappings for just this config type.
   */
  public function assets() {
    return $this->options['assets'];
  }

  /**
   * Gets the list of destination paths that must never be scaffolded.
   *
   * Only evaluated for the root package: the root project may declare
   * destination paths (with fnmatch-style glob support) that no package
   * is allowed to scaffold. Like the other defaults, the empty default
   * value is never written back to the composer.json file.
   *
   * @return string[]
   *   The list of ignored destination paths or patterns.
   */
  public function ignoreFiles() {
    $ignore_files = $this->options['ignore-files'];
    return is_array($ignore_files) ? $ignore_files : [];
  }

  /**
   * Gets all options.
   *
   * @return array
   *   The raw options array.
   */
  public function getAllOptions() {
    return $this->options;
  }

  /**
   * Get modified state.
   *
   * @return bool
   *   The value of the modified state.
   */
  public function getModified() {
    return $this->modified;
  }

  /**
   * Gets the options that have been modified with their current values.
   *
   * @return array
   *   Map of modified option names to their current values.
   */
  public function getModifiedOptions() {
    return array_intersect_key($this->options, $this->modifiedOptions);
  }

  /**
   * Get the package name.
   *
   * @return string
   *   The package's name.
   */
  public function getPackageName() {
    return $this->packageName;
  }

  /**
   * Determines whether a given named location is defined.
   *
   * @param string $name
   *   The location name to search for.
   *
   * @return bool
   *   True if the specified named location exist.
   */
  protected function hasOption($name) {
    return array_key_exists($name, $this->options);
  }

  /**
   * Set an option.
   *
   * @param string $name
   *   The name of the option.
   * @param string $value
   *   The value to set.
   */
  public function setOption($name, $value) {
    if (!$this->hasOption($name) || $this->options[$name] !== $value) {
      $this->modified = TRUE;
      $this->modifiedOptions[$name] = TRUE;
    }
    $this->options[$name] = $value;
  }

  /**
   * Get an option.
   *
   * @param string $name
   *   The name of the option.
   *
   * @return mixed
   *   The value of the option
   */
  public function getOption($name) {
    if (!$this->hasOption($name)) {
      return NULL;
    }
    return $this->options[$name];
  }

}
