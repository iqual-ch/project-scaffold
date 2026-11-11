<?php

namespace Fixtures\EventRecorder;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Records the pre install and update command events it receives.
 *
 * These are the events plugins like cweagans/composer-patches 1.x hook
 * into to (re-)apply patches, so a dependency installation or update run
 * by the scaffold plugin shows up as an additional entry. The events are
 * appended to `event-recorder.log` in the project root.
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * Path to the log file.
   *
   * @var string
   */
  protected $logFile;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->logFile = dirname($composer->getConfig()->get('vendor-dir')) . '/event-recorder.log';
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ScriptEvents::PRE_INSTALL_CMD => 'record',
      ScriptEvents::PRE_UPDATE_CMD => 'record',
    ];
  }

  /**
   * Appends the name of the event to the log file.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function record(Event $event) {
    file_put_contents($this->logFile, $event->getName() . "\n", FILE_APPEND);
  }

}
