<?php

namespace iqual\Composer\ProjectScaffold\Operations;

use Composer\IO\IOInterface;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldFilePath;
use iqual\Composer\ProjectScaffold\ScaffoldFiles\ScaffoldResult;
use iqual\Composer\ProjectScaffold\Options\ScaffoldOptions;

/**
 * Scaffold operation to skip a scaffold file (do nothing).
 *
 * @internal
 */
class SkipOperation extends AbstractOperation {

  /**
   * Identifies Skip operations.
   */
  const ID = 'skip';

  /**
   * The message to output while processing.
   *
   * @var string
   */
  protected $message;

  /**
   * SkipOperation constructor.
   *
   * @param string $message
   *   (optional) A custom message to output while skipping.
   */
  public function __construct($message = "  - Skipping <info>[dest-rel-path]</info>: disabled") {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateContents() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function isTemplated() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function process(ScaffoldFilePath $destination, IOInterface $io, ScaffoldOptions $options, ScaffoldOptions $package_options) {
    $interpolator = $destination->getInterpolator();
    $io->write($interpolator->interpolate($this->message), TRUE, 4);
    return new ScaffoldResult($destination, FALSE);
  }

}
