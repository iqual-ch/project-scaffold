<?php

namespace iqual\Composer\ProjectScaffold\Tests\Functional;

/**
 * Tests scaffolding during `composer create-project`.
 */
class CreateProjectTest extends FunctionalTestBase {

  /**
   * Tests that a new project created from a template is scaffolded.
   */
  public function testCreateProjectScaffoldsNewProject() {
    $template = $this->fixtures->cloneFixtureProject('template-project');
    $workdir = $this->fixtures->tmpDir('create-project');
    $repository = json_encode([
      'type' => 'path',
      'url' => $template,
      'options' => [
        'versions' => ['iqual/test-template' => '1.0.0'],
      ],
    ]);

    $process = $this->runComposer([
      'create-project',
      'iqual/test-template',
      $workdir . '/new-project',
      '--repository', $repository,
      '--no-interaction',
    ], $workdir);
    $output = $this->allOutput($process);

    $this->assertStringContainsString('Processing project scaffold for iqual/test-assets', $output);
    $this->assertSame("# template-project\n\nProject running on PHP 8.2.\n", file_get_contents($workdir . '/new-project/README.md'));
    $this->assertFileExists($workdir . '/new-project/settings.php');
    $this->assertSame("APP_NAME=template-project\nPHP_VERSION=8.2\n", file_get_contents($workdir . '/new-project/.env'));
  }

}
