<?php

namespace iqual\Composer\ProjectScaffold\Options;

/**
 * Flags describing how a single scaffold run was invoked.
 *
 * Unlike ScaffoldOptions, which holds per-package options read from the
 * composer.json files, these flags belong to one invocation of the
 * scaffolding, e.g. the command line options of the project:* commands.
 * Additional per-run flags (such as a future dry-run mode) belong here,
 * so that they reach the operations without new method parameters.
 *
 * @internal
 */
class RunOptions {

  /**
   * Whether the user should always be prompted for the package questions.
   *
   * @var bool
   */
  protected $prompt;

  /**
   * Whether existing destination files should be forcefully re-scaffolded.
   *
   * When enabled, 'add' assets overwrite existing destination files and
   * 'merge' assets replace the destination with a fresh render of their
   * source instead of merging values into it. Local modifications to the
   * scaffolded files are lost.
   *
   * @var bool
   */
  protected $force;

  /**
   * RunOptions constructor.
   *
   * @param bool $prompt
   *   Whether the user should always be prompted for the package questions.
   * @param bool $force
   *   Whether existing destination files should be forcefully re-scaffolded.
   */
  public function __construct(bool $prompt = FALSE, bool $force = FALSE) {
    $this->prompt = $prompt;
    $this->force = $force;
  }

  /**
   * Gets whether the user should always be prompted.
   *
   * @return bool
   *   True if the user should always be prompted.
   */
  public function prompt() {
    return $this->prompt;
  }

  /**
   * Gets whether destination files should be forcefully re-scaffolded.
   *
   * @return bool
   *   True if existing destination files should be re-scaffolded.
   */
  public function force() {
    return $this->force;
  }

  /**
   * Returns a copy of the run options with prompting enabled or disabled.
   *
   * @param bool $prompt
   *   Whether the user should always be prompted for the package questions.
   *
   * @return self
   *   A copy of the run options with the prompt flag set.
   */
  public function withPrompt(bool $prompt) {
    $clone = clone $this;
    $clone->prompt = $prompt;
    return $clone;
  }

}
