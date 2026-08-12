<?php

namespace iqual\Composer\ProjectScaffold\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use iqual\Composer\ProjectScaffold\Handler;
use iqual\Composer\ProjectScaffold\Options\RunOptions;

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
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Hard re-scaffold: overwrite existing "add" files and replace "merge" files with a fresh render. Local modifications to scaffolded files are LOST.')
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
    $handler->scaffold($run_options);
    return 0;
  }

}
