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
   * Whether the run only reports the operations without applying them.
   *
   * When enabled, the operations report what they would do to each
   * destination, but nothing is written to disk: no files are created,
   * overwritten, merged or deleted, and no variables or lock file hash
   * are written back to the composer.json and composer.lock files.
   *
   * @var bool
   */
  protected $dryRun;

  /**
   * RunOptions constructor.
   *
   * @param bool $prompt
   *   Whether the user should always be prompted for the package questions.
   * @param bool $force
   *   Whether existing destination files should be forcefully re-scaffolded.
   * @param bool $dry_run
   *   Whether the run only reports the operations without applying them.
   */
  public function __construct(bool $prompt = FALSE, bool $force = FALSE, bool $dry_run = FALSE) {
    $this->prompt = $prompt;
    $this->force = $force;
    $this->dryRun = $dry_run;
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
   * Gets whether the run only reports the operations without applying them.
   *
   * @return bool
   *   True if nothing should be written to disk.
   */
  public function dryRun() {
    return $this->dryRun;
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
