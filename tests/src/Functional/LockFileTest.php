<?php

namespace iqual\Composer\ProjectScaffold\Tests\Functional;

use Composer\Package\Locker;

/**
 * Tests the composer.lock handling of the options write-back.
 *
 * The sut-lock-refresh fixture requires the test-event-recorder plugin,
 * which logs every pre install and update command event to
 * `event-recorder.log`, the events that plugins like
 * cweagans/composer-patches 1.x use to (re-)apply patches.
 */
class LockFileTest extends FunctionalTestBase {

  /**
   * Gets the recorded install and update command events.
   *
   * @param string $sut
   *   Path to the fixture project.
   *
   * @return string[]
   *   The recorded event names in order.
   */
  protected function recordedEvents(string $sut) {
    $log = $sut . '/event-recorder.log';
    return file_exists($log) ? file($log, FILE_IGNORE_NEW_LINES) : [];
  }

  /**
   * Clears the recorded install and update command events.
   *
   * @param string $sut
   *   Path to the fixture project.
   */
  protected function resetRecordedEvents(string $sut) {
    if (file_exists($sut . '/event-recorder.log')) {
      unlink($sut . '/event-recorder.log');
    }
  }

  /**
   * Reads the composer.lock file of a fixture project.
   *
   * @param string $sut
   *   Path to the fixture project.
   *
   * @return array
   *   The lock file data.
   */
  protected function readLock(string $sut) {
    return json_decode(file_get_contents($sut . '/composer.lock'), TRUE);
  }

  /**
   * Asserts that the lock file is up to date with the composer.json file.
   *
   * @param string $sut
   *   Path to the fixture project.
   */
  protected function assertLockFileIsFresh(string $sut) {
    $this->assertSame(Locker::getContentHash(file_get_contents($sut . '/composer.json')), $this->readLock($sut)['content-hash']);
    $process = $this->runComposer(['install', '--dry-run', '--no-interaction'], $sut);
    $this->assertStringNotContainsString('not up to date', $this->allOutput($process));
  }

  /**
   * Tests that a write-back during `composer update` runs no nested update.
   *
   * Installing the require-dev asset package on update asks its questions
   * and writes the new answer back to the composer.json file. The lock file
   * hash must be refreshed without dispatching the install or update events
   * a second time.
   */
  public function testWriteBackDuringUpdateRunsNoNestedUpdate() {
    $sut = $this->fixtures->cloneFixtureProject('sut-lock-refresh');
    $this->runComposer(['install', '--no-dev', '--no-interaction'], $sut);
    $this->resetRecordedEvents($sut);
    $lock = $this->readLock($sut);

    $process = $this->runComposer(['update', '--no-interaction'], $sut);
    $output = $this->allOutput($process);

    $this->assertStringContainsString("Writing variables to 'extra' section of top-level composer.json file.", $output);
    $this->assertStringContainsString('Updating the hash in the composer.lock file.', $output);
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $this->assertSame('https://lock-refresh-project.example.com', $definition['extra']['project-scaffold']['url']);

    // The update command events were dispatched exactly once.
    $this->assertSame(['pre-update-cmd'], $this->recordedEvents($sut));

    // Only the hash of the lock file changed.
    $this->assertSame($lock['packages'], $this->readLock($sut)['packages']);
    $this->assertSame($lock['packages-dev'], $this->readLock($sut)['packages-dev']);
    $this->assertLockFileIsFresh($sut);
  }

  /**
   * Tests that a write-back of `composer project:init` runs no update.
   */
  public function testWriteBackDuringProjectInitRunsNoUpdate() {
    $sut = $this->fixtures->cloneFixtureProject('sut-lock-refresh');
    $this->runComposer(['install', '--no-interaction'], $sut);
    $this->resetRecordedEvents($sut);
    $lock = $this->readLock($sut);

    $process = $this->runComposer(['project:init', '--no-interaction'], $sut);
    $output = $this->allOutput($process);

    $this->assertStringContainsString("Writing variables to 'extra' section of top-level composer.json file.", $output);
    $this->assertStringContainsString('Updating the hash in the composer.lock file.', $output);

    // No install or update command events were dispatched at all.
    $this->assertSame([], $this->recordedEvents($sut));

    // Only the hash of the lock file changed.
    $this->assertSame($lock['packages'], $this->readLock($sut)['packages']);
    $this->assertSame($lock['packages-dev'], $this->readLock($sut)['packages-dev']);
    $this->assertLockFileIsFresh($sut);
  }

}
