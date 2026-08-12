<?php

namespace iqual\Composer\ProjectScaffold\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use iqual\Composer\ProjectScaffold\Handler;
use iqual\Composer\ProjectScaffold\Options\RunOptions;

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
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Hard re-scaffold: overwrite existing "add" files and replace "merge" files with a fresh render. Local modifications to scaffolded files are LOST.')
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

With <info>--force</info> a hard re-scaffold is performed: <info>add</info> assets
overwrite files that already exist at their destination, and <info>merge</info>
assets replace the destination with a fresh render of their source instead of
merging values into it.

<warning>Warning: with --force any local modifications to scaffolded files are
lost, including values that were merged into .env, JSON or YAML files.</warning>
EOT
            );

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $run_options = new RunOptions(force: (bool) $input->getOption('force'));
    $handler = new Handler($this->requireComposer(), $this->getIO());
    $handler->init($run_options);
    return 0;
  }

}
