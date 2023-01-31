<?php

namespace iqual\Composer\ProjectScaffold;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use iqual\Composer\ProjectScaffold\Commands\ComposerScaffoldCommand;
use iqual\Composer\ProjectScaffold\Commands\ComposerInitCommand;

/**
 * List of all commands provided by this package.
 *
 * @internal
 */
class CommandProvider implements CommandProviderCapability {

  /**
   * {@inheritdoc}
   */
  public function getCommands() {
    return [
      new ComposerScaffoldCommand(),
      new ComposerInitCommand(),
    ];
  }

}
