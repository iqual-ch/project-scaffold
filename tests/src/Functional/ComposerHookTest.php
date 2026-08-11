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
