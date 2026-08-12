<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use Composer\Script\Event;

/**
 * Script callbacks for testing the pre/post-project-scaffold-cmd hooks.
 */
class ScaffoldHooks {

  /**
   * Callback for the scaffold command hooks.
   *
   * @param \Composer\Script\Event $event
   *   The Composer script event.
   */
  public static function hook(Event $event) {
    $event->getIO()->write(sprintf('%s hook for %s', $event->getName(), $event->getComposer()->getPackage()->getName()));
  }

}
