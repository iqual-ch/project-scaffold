<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Platform;
use iqual\Composer\ProjectScaffold\Plugin;
use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests the auto-scaffold opt-out of the plugin's post command events.
 *
 * Automatic scaffolding on `composer install`/`update` can be disabled
 * with the `extra.project-scaffold.auto-scaffold` key of the root
 * composer.json or the PROJECT_SCAFFOLD_AUTO environment variable, where
 * the environment variable takes precedence over the key.
 */
class AutoScaffoldTest extends TestCase {

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
    Platform::clearEnv('PROJECT_SCAFFOLD_AUTO');
    $this->fixtures->tearDown();
  }

  /**
   * Runs the plugin's post command event against the sut-preset fixture.
   *
   * @param bool|null $auto_scaffold
   *   Value for the `auto-scaffold` key, or NULL to leave the key unset.
   *
   * @return array
   *   The SUT directory and the BufferIO object.
   */
  protected function runPostCmd(?bool $auto_scaffold) {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    if ($auto_scaffold !== NULL) {
      $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
      $definition['extra']['project-scaffold']['auto-scaffold'] = $auto_scaffold;
      file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');

    $plugin = new Plugin();
    $plugin->activate($composer, $io);
    $plugin->postCmd(new Event(ScriptEvents::POST_INSTALL_CMD, $composer, $io));

    return [$sut, $io];
  }

  /**
   * Asserts that the post command event scaffolded the project.
   */
  protected function assertScaffolded(string $sut, string $output) {
    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets:', $output);
    $this->assertStringNotContainsString('Skipping automatic project scaffolding', $output);
    $this->assertFileExists($sut . '/README.md');
  }

  /**
   * Asserts that the post command event did not scaffold the project.
   */
  protected function assertNotScaffolded(string $sut, string $output) {
    $this->assertStringContainsString('Skipping automatic project scaffolding because it is disabled.', $output);
    $this->assertStringNotContainsString('Processing project scaffold', $output);
    $this->assertFileDoesNotExist($sut . '/README.md');
  }

  /**
   * Tests that automatic scaffolding is enabled by default.
   */
  public function testAutoScaffoldIsEnabledByDefault() {
    [$sut, $io] = $this->runPostCmd(NULL);
    $this->assertScaffolded($sut, $io->getOutput());
  }

  /**
   * Tests that the auto-scaffold key disables the post command scaffolding.
   */
  public function testConfigKeyDisablesAutoScaffold() {
    [$sut, $io] = $this->runPostCmd(FALSE);
    $this->assertNotScaffolded($sut, $io->getOutput());
  }

  /**
   * Tests that the environment variable disables the post command scaffolding.
   */
  public function testEnvVarDisablesAutoScaffold() {
    Platform::putEnv('PROJECT_SCAFFOLD_AUTO', '0');
    [$sut, $io] = $this->runPostCmd(NULL);
    $this->assertNotScaffolded($sut, $io->getOutput());
  }

  /**
   * Tests that the "false" spelling of the environment variable disables too.
   */
  public function testEnvVarFalseDisablesAutoScaffold() {
    Platform::putEnv('PROJECT_SCAFFOLD_AUTO', 'false');
    [$sut, $io] = $this->runPostCmd(NULL);
    $this->assertNotScaffolded($sut, $io->getOutput());
  }

  /**
   * Tests that the environment variable wins over an enabling config key.
   */
  public function testEnvVarOverridesEnablingConfigKey() {
    Platform::putEnv('PROJECT_SCAFFOLD_AUTO', '0');
    [$sut, $io] = $this->runPostCmd(TRUE);
    $this->assertNotScaffolded($sut, $io->getOutput());
  }

  /**
   * Tests that the environment variable wins over a disabling config key.
   */
  public function testEnvVarOverridesDisablingConfigKey() {
    Platform::putEnv('PROJECT_SCAFFOLD_AUTO', '1');
    [$sut, $io] = $this->runPostCmd(FALSE);
    $this->assertScaffolded($sut, $io->getOutput());
  }

  /**
   * Tests that an explicit scaffold run ignores a disabled auto-scaffold.
   */
  public function testExplicitScaffoldIgnoresOptOut() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['auto-scaffold'] = FALSE;
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);

    $this->assertScaffolded($sut, $io->getOutput());
  }

}
