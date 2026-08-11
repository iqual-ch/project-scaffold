<?php

namespace iqual\Composer\ProjectScaffold\Tests\Functional;

use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Base class for tests running the real composer executable.
 */
abstract class FunctionalTestBase extends TestCase {

  /**
   * The fixtures helper.
   *
   * @var \iqual\Composer\ProjectScaffold\Tests\Fixtures
   */
  protected $fixtures;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fixtures = new Fixtures();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->fixtures->tearDown();
  }

  /**
   * Gets the composer executable to run.
   *
   * @return string
   *   Name or path of the composer executable.
   */
  protected function composerBinary() {
    return getenv('COMPOSER_TEST_BINARY') ?: 'composer';
  }

  /**
   * Runs a composer command against a fixture project.
   *
   * The Composer home and cache directories are isolated per fixture
   * project, so nothing of the user's Composer setup leaks into the test.
   *
   * @param string[] $args
   *   Arguments to pass to composer.
   * @param string $cwd
   *   The working directory to run in.
   * @param bool $must_succeed
   *   Whether a non-zero exit code should fail the test.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  protected function runComposer(array $args, string $cwd, bool $must_succeed = TRUE) {
    $env = [
      'COMPOSER_HOME' => $cwd . '/.composer-home',
      'COMPOSER_CACHE_DIR' => $cwd . '/.composer-home/cache',
    ];
    $process = new Process(array_merge([$this->composerBinary(), '--no-ansi'], $args), $cwd, $env, NULL, 300);
    $process->run();
    if ($must_succeed && !$process->isSuccessful()) {
      $this->fail("Composer command 'composer " . implode(' ', $args) . "' failed:\n" . $this->allOutput($process));
    }
    return $process;
  }

  /**
   * Gets the combined standard and error output of a process.
   *
   * @param \Symfony\Component\Process\Process $process
   *   The finished process.
   *
   * @return string
   *   The combined output.
   */
  protected function allOutput(Process $process) {
    return $process->getOutput() . "\n" . $process->getErrorOutput();
  }

}
