<?php

namespace iqual\Composer\ProjectScaffold\Util;

use Composer\IO\IOInterface;
use Symfony\Component\Console\Output\Output;

/**
 * Adapts a Composer I/O service to a Symfony console output.
 *
 * Allows running console commands whose output is routed through the
 * I/O service of the current run, so that its verbosity settings and
 * output capturing (e.g. in tests) are respected.
 *
 * @internal
 */
class IOOutput extends Output {

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * IOOutput constructor.
   *
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service the output is forwarded to.
   */
  public function __construct(IOInterface $io) {
    $this->io = $io;
    parent::__construct(static::getVerbosityFromIo($io), $io->isDecorated());
  }

  /**
   * Maps the verbosity of an I/O service to an output verbosity.
   *
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service.
   *
   * @return int
   *   One of the OutputInterface::VERBOSITY_* constants.
   */
  protected static function getVerbosityFromIo(IOInterface $io) {
    if ($io->isDebug()) {
      return self::VERBOSITY_DEBUG;
    }
    if ($io->isVeryVerbose()) {
      return self::VERBOSITY_VERY_VERBOSE;
    }
    if ($io->isVerbose()) {
      return self::VERBOSITY_VERBOSE;
    }
    return self::VERBOSITY_NORMAL;
  }

  /**
   * {@inheritdoc}
   */
  protected function doWrite(string $message, bool $newline): void {
    $this->io->write($message, $newline);
  }

}
