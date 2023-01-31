<?php

namespace iqual\Composer\ProjectScaffold\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use iqual\Composer\ProjectScaffold\Handler;

/**
 * The "project:init" command class.
 *
 * Manually run the init operation that normally happens after
 * 'composer install'.
 *
 * @internal
 */
class ComposerInitCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('project:init')
      ->setAliases(['project:update'])
      ->setDescription('Update the iqual init files.')
      ->setHelp(
        <<<EOT
The <info>project:init</info> command places the init files in their
respective locations according to the layout stipulated in the composer.json
file.

<info>php composer.phar project:init</info>

It is usually not necessary to call <info>project:init</info> manually,
because it is called automatically as needed, e.g. after an <info>install</info>
or <info>update</info> command. Note, though, that only packages explicitly
allowed to init in the top-level composer.json will be processed by this
command.
EOT
            );

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $handler = new Handler($this->getComposer(), $this->getIO());
    $handler->init();
    return 0;
  }

}
