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
use Composer\Util\Platform;
use iqual\Composer\ProjectScaffold\CommandProvider as ScaffoldCommandProvider;
use iqual\Composer\ProjectScaffold\Options\ManageOptions;

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
   * Record whether the current execution is a lock update.
   *
   * @var bool
   */
  protected $lockUpdateCommand;

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
    $this->lockUpdateCommand = FALSE;
    $this->preloadClasses();
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
    // Do not scaffold if it's a `composer update --lock` command to prevent loops.
    if ($this->lockUpdateCommand) {
      return;
    }
    // Do not scaffold when automatic scaffolding has been disabled via the
    // environment or the root composer.json. Explicit commands like
    // `composer project:scaffold` are not affected by this.
    if (!$this->isAutoScaffoldEnabled()) {
      $this->io->write("Skipping automatic project scaffolding because it is disabled.", TRUE, IOInterface::VERBOSE);
      return;
    }
    $this->handler()->scaffold();
  }

  /**
   * Determines whether automatic scaffolding on post command events is enabled.
   *
   * The PROJECT_SCAFFOLD_AUTO environment variable ("0" or "false" disables,
   * "1" or "true" enables) takes precedence over the `auto-scaffold` key in
   * the `extra.project-scaffold` section of the root composer.json. When
   * neither is set, automatic scaffolding is enabled.
   *
   * @return bool
   *   True if automatic scaffolding is enabled.
   */
  protected function isAutoScaffoldEnabled() {
    $env = Platform::getEnv('PROJECT_SCAFFOLD_AUTO');
    if ($env !== FALSE && $env !== '') {
      return !in_array(strtolower($env), ['0', 'false'], TRUE);
    }
    return (new ManageOptions($this->composer))->getOptions()->autoScaffold();
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
      if ($event->getCommandName() == 'update' && $event->getInput()->hasOption("lock") && $event->getInput()->getOption("lock")) {
        $this->lockUpdateCommand = TRUE;
      }
    }
  }

  /**
   * Preloads all of the plugin's class files into memory.
   *
   * If this plugin is updated within the same Composer process that has it
   * activated, PHP may autoload new class files against old, already loaded
   * ones mid-run. Eagerly loading every class file up front ensures that all
   * of the plugin's code is in memory before an update can swap the files on
   * disk.
   */
  private function preloadClasses() {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->getExtension() === 'php' && $file->getRealPath() !== __FILE__) {
        require_once $file->getRealPath();
      }
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
