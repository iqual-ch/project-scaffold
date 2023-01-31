<?php

namespace iqual\Composer\ProjectScaffold;

use Composer\Installer\PackageEvent;

/**
 * Interface for post package event listeners.
 *
 * @see \iqual\Composer\ProjectScaffold\Handler::onPostPackageEvent
 *
 * @internal
 */
interface PostPackageEventListenerInterface {

  /**
   * Handles package events during a composer operation.
   *
   * Covers 'composer require' and 'composer update' operation.
   *
   * @param \Composer\Installer\PackageEvent $event
   *   Composer package event sent on install/update/remove.
   * @param string $command
   *   Composer command that was called, either require or update.
   */
  public function event(PackageEvent $event, string $command);

}
