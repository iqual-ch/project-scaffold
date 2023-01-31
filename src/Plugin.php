<?php

namespace iqual\Composer\ProjectScaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use iqual\Composer\ProjectScaffold\CommandProvider as ScaffoldCommandProvider;

/**
 * Composer plugin for handling drupal scaffold.
 *
 * @internal
 */
class Plugin implements PluginInterface, EventSubscriberInterface, Capable {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * The Composer Scaffold handler.
   *
   * @var \iqual\Composer\ProjectScaffold\Handler
   */
  protected $handler;

  /**
   * Record whether a command was called that shoud be listened to.
   *
   * @var bool
   */
  protected $eventCommandWasCalled;

  /**
   * Record what command was called.
   *
   * @var string
   */
  protected $calledCommand;

  /**
   * Priority that plugin uses to register callbacks.
   *
   * @param int
   */
  private const CALLBACK_PRIORITY = -999;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $this->eventCommandWasCalled = FALSE;
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
  public function getCapabilities() {
    return [CommandProvider::class => ScaffoldCommandProvider::class];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Important note: We only instantiate our handler on "post" events.
    return [
      ScriptEvents::POST_UPDATE_CMD => [
        'postCmd', self::CALLBACK_PRIORITY,
      ],
      ScriptEvents::POST_INSTALL_CMD => [
        'postCmd', self::CALLBACK_PRIORITY,
      ],
      PackageEvents::POST_PACKAGE_INSTALL => [
        'postPackage', self::CALLBACK_PRIORITY,
      ],
      PackageEvents::POST_PACKAGE_UPDATE => [
        'postPackage', self::CALLBACK_PRIORITY,
      ],
      PluginEvents::COMMAND => [
        'onCommand', self::CALLBACK_PRIORITY,
      ],
    ];
  }

  /**
   * Post command event callback.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function postCmd(Event $event) {
    $this->handler()->scaffold();
    // $this->handler()->init();
  }

  /**
   * Post package event behavior.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   */
  public function postPackage(PackageEvent $event) {
    $this->handler()->onPostPackageEvent($event);
  }

  /**
   * Pre command event callback.
   *
   * @param \Composer\Plugin\CommandEvent $event
   *   The Composer command event.
   */
  public function onCommand(CommandEvent $event) {
    if ($event->getCommandName() == 'require' || $event->getCommandName() == 'update') {
      if ($this->handler) {
        throw new \Error('Core Scaffold Plugin handler instantiated too early. See https://www.drupal.org/project/drupal/issues/3104922');
      }
      $this->eventCommandWasCalled = TRUE;
      $this->calledCommand = $event->getCommandName();
    }
  }

  /**
   * Instantiation of handler object.
   *
   * Lazy-instantiate the handler object. It is dangerous to update a Composer
   * plugin if it loads any classes prior to the `composer update` operation,
   * and later tries to use them in a post-update hook.
   */
  protected function handler() {
    if (!$this->handler) {
      $this->handler = new Handler($this->composer, $this->io);
      // On instantiation of our handler, notify it if the 'require' command
      // was executed.
      if ($this->eventCommandWasCalled) {
        $this->handler->addEventListeners($this->calledCommand);
      }
    }
    return $this->handler;
  }

}
