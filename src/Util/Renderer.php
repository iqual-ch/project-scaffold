<?php

namespace iqual\Composer\ProjectScaffold\Util;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\TwigTest;

/**
 * Render templates to a string.
 *
 * Supports twig templates (i.e. `.twig`).
 *
 * @internal
 */
class Renderer {

  /**
   * The associative array of replacements.
   *
   * @var array
   */
  protected $data = [];

  /**
   * Renderer constructor.
   */
  public function __construct(array $default_data = []) {
    if (!empty($default_data)) {
      self::setData($default_data);
    }
  }

  /**
   * Sets the data set to use when rendering.
   *
   * @param array $data
   *   The key:value pairs to use when rendering.
   *
   * @return $this
   */
  public function setData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Adds to the data set to use when rendering.
   *
   * @param array $data
   *   The key:value pairs to use when rendering.
   *
   * @return $this
   */
  public function addData(array $data) {
    $this->data = array_merge($this->data, $data);
    return $this;
  }

  /**
   * Renders a single file using Twig.
   *
   * @param string $file_path
   *   Absolute path to the file.
   * @param string $type
   *   The template type.
   * @param array $extra
   *   Data to use for rendering in addition to whatever was provided to
   *   self::setData().
   *
   * @return string
   *   The contents after replacements have been made.
   */
  public function render(string $file_path, string $type = "twig", array $extra = []) {
    $data = $extra + $this->data;
    if ($type == "twig") {
      // Load root filesystem without caching.
      $loader = new FilesystemLoader([''], '/');
      $twig = new Environment($loader, ["cache" => FALSE]);

      // Add test for checking if files exist.
      $filter = new TwigTest('existing_file', function ($file) {
        return file_exists($file);
      });
      $twig->addTest($filter);

      // Render template from file_path using the given data.
      return $twig->render($file_path, $data);
    }
    else {
      return file_get_contents($file_path);
    }
  }

}
