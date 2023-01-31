<?php

namespace iqual\Composer\ProjectScaffold\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use iqual\Composer\ProjectScaffold\Handler;

/**
 * The "project:scaffold" command class.
 *
 * Manually run the scaffold operation that normally happens after
 * 'composer install'.
 *
 * @internal
 */
class ComposerScaffoldCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('project:scaffold')
      ->setDescription('Update the iqual scaffold files.')
      ->setHelp(
        <<<EOT
The <info>project:scaffold</info> command places the scaffold files in their
respective locations according to the layout stipulated in the composer.json
file.

<info>php composer.phar project:scaffold</info>

It is usually not necessary to call <info>project:scaffold</info> manually,
because it is called automatically as needed, e.g. after an <info>install</info>
or <info>update</info> command. Note, though, that only packages explicitly
allowed to scaffold in the top-level composer.json will be processed by this
command.
EOT
            );

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $handler = new Handler($this->getComposer(), $this->getIO());
    $handler->scaffold();
    return 0;
  }

}
