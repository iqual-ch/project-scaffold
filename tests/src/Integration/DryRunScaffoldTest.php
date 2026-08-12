<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests the dry-run mode of the scaffold and init operations.
 */
class DryRunScaffoldTest extends TestCase {

  /**
   * The fixtures helper.
   *
   * @var \iqual\Composer\ProjectScaffold\Tests\Fixtures
   */
  protected $fixtures;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fixtures = new Fixtures();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->fixtures->tearDown();
  }

  /**
   * Runs the scaffold operation on a (cloned) fixture project.
   */
  protected function scaffold(string $sut, bool $force = FALSE, bool $dry_run = FALSE, bool $init = FALSE, array $user_inputs = []) {
    $io = $this->fixtures->io($user_inputs);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io, $init, $force, $dry_run);
    return $io;
  }

  /**
   * Reads the contents of a list of project files.
   */
  protected function readFiles(string $sut, array $files) {
    $contents = [];
    foreach ($files as $file) {
      $contents[$file] = file_get_contents($sut . '/' . $file);
    }
    return $contents;
  }

  /**
   * Tests that a dry-run reports all operations without changing any files.
   */
  public function testDryRunReportsOperationsWithoutWriting() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $existing_files = ['composer.json', '.env', '.gitignore', 'config.json', 'services.yml', 'delete-me.txt', 'app/web/index.php'];
    $original_contents = $this->readFiles($sut, $existing_files);

    $io = $this->scaffold($sut, dry_run: TRUE);
    $output = $io->getOutput();

    // The would-be actions are reported per destination.
    $this->assertStringContainsString('Performing a dry-run, no changes will be written.', $output);
    $this->assertStringContainsString('(Add) (dry-run)', $output);
    $this->assertStringContainsString('(Replace) (dry-run)', $output);
    $this->assertStringContainsString('(Merge) (dry-run)', $output);
    $this->assertStringContainsString('Deleting', $output);
    $this->assertStringContainsString('delete-me.txt', $output);
    $this->assertStringContainsString('All assets have been scaffolded.', $output);

    // No files were created or deleted.
    $this->assertFileDoesNotExist($sut . '/settings.php');
    $this->assertFileDoesNotExist($sut . '/README.md');
    $this->assertFileDoesNotExist($sut . '/Makefile');
    $this->assertFileExists($sut . '/delete-me.txt');
    $this->assertFileDoesNotExist($sut . '/composer.lock');

    // No existing file (including the composer.json file) was modified.
    $this->assertSame($original_contents, $this->readFiles($sut, $existing_files));

    // A subsequent real run performs the reported actions.
    $this->scaffold($sut);
    $this->assertFileExists($sut . '/settings.php');
    $this->assertFileDoesNotExist($sut . '/delete-me.txt');
    $this->assertSame("APP_NAME=preset-project\nDEBUG=true\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
  }

  /**
   * Tests that a dry-run combined with --force reports the forced actions.
   */
  public function testDryRunWithForceReportsWithoutWriting() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $this->scaffold($sut);

    // Modify an 'add' destination; the 'merge' destinations contain the
    // locally merged values from the first run.
    file_put_contents($sut . '/settings.php', "<?php\n// Locally modified.\n");
    $watched_files = ['composer.json', 'settings.php', '.env', '.gitignore', 'config.json', 'services.yml'];
    $original_contents = $this->readFiles($sut, $watched_files);

    $io = $this->scaffold($sut, force: TRUE, dry_run: TRUE);
    $output = $io->getOutput();

    // The forced overwrite of the 'add' destination and the fresh render
    // of the 'merge' destinations are reported, but nothing is written.
    $this->assertStringContainsString('(Add) (dry-run)', $output);
    $this->assertStringContainsString('(Merge) (dry-run)', $output);
    $this->assertSame($original_contents, $this->readFiles($sut, $watched_files));
  }

  /**
   * Tests that a dry-run of the init operation does not save the answers.
   */
  public function testInitDryRunDoesNotWriteAnswers() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $original_composer_json = file_get_contents($sut . '/composer.json');

    // Keep the preset name and PHP version, choose 'none' for deployment.
    $io = $this->scaffold($sut, dry_run: TRUE, init: TRUE, user_inputs: ['', '', '0']);
    $output = $io->getOutput();

    // The questions are still asked and the write-back is reported, but
    // neither the answers nor any files are written.
    $this->assertStringContainsString('Configure iqual/test-assets:', $output);
    $this->assertStringContainsString("Writing variables to 'extra' section of top-level composer.json file. (dry-run)", $output);
    $this->assertSame($original_composer_json, file_get_contents($sut . '/composer.json'));
    $this->assertFileDoesNotExist($sut . '/composer.lock');
    $this->assertFileDoesNotExist($sut . '/README.md');
    $this->assertFileDoesNotExist($sut . '/settings.php');
  }

  /**
   * Tests that a dry-run does not dispatch the scaffold command scripts.
   */
  public function testDryRunDoesNotDispatchScripts() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['scripts'] = [
      'pre-project-scaffold-cmd' => 'iqual\\Composer\\ProjectScaffold\\Tests\\Integration\\ScaffoldHooks::hook',
      'post-project-scaffold-cmd' => 'iqual\\Composer\\ProjectScaffold\\Tests\\Integration\\ScaffoldHooks::hook',
    ];
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $io = $this->scaffold($sut, dry_run: TRUE);
    $output = $io->getOutput();

    $this->assertStringNotContainsString('pre-project-scaffold-cmd hook', $output);
    $this->assertStringNotContainsString('post-project-scaffold-cmd hook', $output);

    // A real run on the same project still dispatches both scripts.
    $io = $this->scaffold($sut);
    $output = $io->getOutput();

    $this->assertStringContainsString('pre-project-scaffold-cmd hook for fixtures/sut-preset', $output);
    $this->assertStringContainsString('post-project-scaffold-cmd hook for fixtures/sut-preset', $output);
  }

}
