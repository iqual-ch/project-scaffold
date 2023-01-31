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
   * Creates a new scaffold options object with some values overridden.
   *
   * @param array $options
   *   Override values.
   *
   * @return self
   *   The scaffold options object representing the provided scaffold options
   */
  protected function override(array $options) {
    return new self($options + $this->options);
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
   * Gets a all options.
   *
   * @return string
   *   The value of the provided named location.
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
    if ($this->hasOption($name)) {
      if ($this->options[$name] !== $value) {
        $this->modified = TRUE;
      }
    }
    else {
      $this->modified = TRUE;
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
