<?php

namespace iqual\Composer\ProjectScaffold\Tests\Functional;

/**
 * Tests that the plugin hooks into real composer commands.
 */
class ComposerHookTest extends FunctionalTestBase {

  /**
   * Installs the sut-functional fixture project.
   *
   * @return string
   *   Path to the installed fixture project.
   */
  protected function installFunctionalProject() {
    $sut = $this->fixtures->cloneFixtureProject('sut-functional');
    $this->runComposer(['install', '--no-interaction'], $sut);
    return $sut;
  }

  /**
   * Tests that `composer install` scaffolds the project.
   */
  public function testComposerInstallScaffoldsProject() {
    $sut = $this->fixtures->cloneFixtureProject('sut-functional');
    $process = $this->runComposer(['install', '--no-interaction'], $sut);
    $output = $this->allOutput($process);

    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets', $output);
    $this->assertStringContainsString('All assets have been scaffolded.', $output);

    // Twig templates are rendered with the preset variables.
    $this->assertSame("# functional-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));

    // Add and merge assets are created at their scaffold locations.
    $this->assertFileExists($sut . '/settings.php');
    $this->assertFileExists($sut . '/app/web/index.php');
    $this->assertSame("APP_NAME=functional-project\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
  }

  /**
   * Tests that `composer project:scaffold` restores managed files.
   */
  public function testProjectScaffoldCommandRestoresFiles() {
    $sut = $this->installFunctionalProject();

    file_put_contents($sut . '/README.md', 'manually changed');
    $process = $this->runComposer(['project:scaffold'], $sut);

    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets', $this->allOutput($process));
    $this->assertSame("# functional-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that `composer project:scaffold --force` re-scaffolds everything.
   */
  public function testProjectScaffoldForceOverwritesLocalModifications() {
    $sut = $this->installFunctionalProject();

    // Modify an 'add' destination and merge a local value into a 'merge'
    // destination.
    file_put_contents($sut . '/settings.php', "<?php\n// Locally modified.\n");
    file_put_contents($sut . '/.env', file_get_contents($sut . '/.env') . "LOCAL_VALUE=1\n");

    // Without --force both local modifications survive a scaffold run.
    $this->runComposer(['project:scaffold'], $sut);
    $this->assertSame("<?php\n// Locally modified.\n", file_get_contents($sut . '/settings.php'));
    $this->assertSame("APP_NAME=functional-project\nPHP_VERSION=8.2\nLOCAL_VALUE=1\n", file_get_contents($sut . '/.env'));

    // With --force the 'add' destination is overwritten and the 'merge'
    // destination is replaced with a fresh render of its source.
    $process = $this->runComposer(['project:scaffold', '--force'], $sut);
    $this->assertStringContainsString('All assets have been scaffolded.', $this->allOutput($process));
    $this->assertStringContainsString('Default settings scaffolded once', file_get_contents($sut . '/settings.php'));
    $this->assertSame("APP_NAME=functional-project\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
  }

  /**
   * Tests that `composer project:init --force` re-scaffolds everything.
   */
  public function testProjectInitForceOverwritesLocalModifications() {
    $sut = $this->installFunctionalProject();

    file_put_contents($sut . '/settings.php', "<?php\n// Locally modified.\n");
    $process = $this->runComposer(['project:init', '--force', '--no-interaction'], $sut);

    $this->assertStringContainsString('All assets have been scaffolded.', $this->allOutput($process));
    $this->assertStringContainsString('Default settings scaffolded once', file_get_contents($sut . '/settings.php'));
    $this->assertSame("APP_NAME=functional-project\nPHP_VERSION=8.2\n", file_get_contents($sut . '/.env'));
  }

  /**
   * Tests that `composer project:scaffold --dry-run` changes no files.
   */
  public function testProjectScaffoldDryRunDoesNotChangeFiles() {
    $sut = $this->installFunctionalProject();

    file_put_contents($sut . '/README.md', 'manually changed');
    $original_composer_json = file_get_contents($sut . '/composer.json');
    $original_composer_lock = file_get_contents($sut . '/composer.lock');

    $process = $this->runComposer(['project:scaffold', '--dry-run'], $sut);
    $output = $this->allOutput($process);

    // The would-be replace is reported, but the file is not restored and
    // the composer files are untouched.
    $this->assertStringContainsString('Performing a dry-run, no changes will be written.', $output);
    $this->assertStringContainsString('(Replace) (dry-run)', $output);
    $this->assertSame('manually changed', file_get_contents($sut . '/README.md'));
    $this->assertSame($original_composer_json, file_get_contents($sut . '/composer.json'));
    $this->assertSame($original_composer_lock, file_get_contents($sut . '/composer.lock'));
  }

  /**
   * Tests that `composer project:init --dry-run` does not save the answers.
   */
  public function testProjectInitDryRunDoesNotWriteVariables() {
    $sut = $this->installFunctionalProject();

    $original_composer_json = file_get_contents($sut . '/composer.json');
    $original_composer_lock = file_get_contents($sut . '/composer.lock');

    $process = $this->runComposer(['project:init', '--dry-run', '--no-interaction'], $sut);
    $output = $this->allOutput($process);

    // A real init run writes the collected variables to the composer.json
    // file and updates the lock file hash; the dry-run only reports it.
    $this->assertStringContainsString("Writing variables to 'extra' section of top-level composer.json file. (dry-run)", $output);
    $this->assertSame($original_composer_json, file_get_contents($sut . '/composer.json'));
    $this->assertSame($original_composer_lock, file_get_contents($sut . '/composer.lock'));
  }

  /**
   * Tests that `composer project:init` runs non-interactively.
   */
  public function testProjectInitCommandRuns() {
    $sut = $this->installFunctionalProject();
    $process = $this->runComposer(['project:init', '--no-interaction'], $sut);
    $output = $this->allOutput($process);

    $this->assertStringContainsString('All assets have been scaffolded.', $output);
    $this->assertSame("# functional-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that `composer update` re-runs the scaffolding.
   */
  public function testComposerUpdateScaffoldsProject() {
    $sut = $this->installFunctionalProject();

    file_put_contents($sut . '/README.md', 'manually changed');
    $process = $this->runComposer(['update', '--no-interaction'], $sut);

    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets', $this->allOutput($process));
    $this->assertSame("# functional-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Clones the sut-functional fixture with auto-scaffold disabled.
   *
   * @return string
   *   Path to the cloned fixture project.
   */
  protected function cloneProjectWithAutoScaffoldDisabled() {
    $sut = $this->fixtures->cloneFixtureProject('sut-functional');
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['auto-scaffold'] = FALSE;
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $sut;
  }

  /**
   * Tests that the auto-scaffold key disables scaffolding on install.
   */
  public function testAutoScaffoldConfigKeyDisablesInstallScaffolding() {
    $sut = $this->cloneProjectWithAutoScaffoldDisabled();
    $process = $this->runComposer(['install', '--no-interaction'], $sut);

    $this->assertStringNotContainsString('Processing project scaffold', $this->allOutput($process));
    $this->assertFileDoesNotExist($sut . '/README.md');

    // The explicit scaffold command is not affected by the opt-out.
    $process = $this->runComposer(['project:scaffold'], $sut);
    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets', $this->allOutput($process));
    $this->assertSame("# functional-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that the environment variable disables scaffolding on install.
   */
  public function testAutoScaffoldEnvVarDisablesInstallScaffolding() {
    $sut = $this->fixtures->cloneFixtureProject('sut-functional');
    $process = $this->runComposer(['install', '--no-interaction'], $sut, TRUE, ['PROJECT_SCAFFOLD_AUTO' => '0']);

    $this->assertStringNotContainsString('Processing project scaffold', $this->allOutput($process));
    $this->assertFileDoesNotExist($sut . '/README.md');
  }

  /**
   * Tests that the environment variable wins over the auto-scaffold key.
   */
  public function testAutoScaffoldEnvVarOverridesConfigKey() {
    $sut = $this->cloneProjectWithAutoScaffoldDisabled();
    $process = $this->runComposer(['install', '--no-interaction'], $sut, TRUE, ['PROJECT_SCAFFOLD_AUTO' => '1']);

    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets', $this->allOutput($process));
    $this->assertSame("# functional-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that `composer update --lock` does not scaffold.
   */
  public function testUpdateLockDoesNotScaffold() {
    $sut = $this->installFunctionalProject();

    file_put_contents($sut . '/README.md', 'manually changed');
    $process = $this->runComposer(['update', '--lock', '--no-interaction'], $sut);

    $this->assertStringNotContainsString('Processing project scaffold', $this->allOutput($process));
    $this->assertSame('manually changed', file_get_contents($sut . '/README.md'));
  }

}
