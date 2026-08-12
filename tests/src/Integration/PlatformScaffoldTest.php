<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests the scaffold operation with production-shaped assets.
 *
 * The test-assets-platform fixture package mirrors the shapes used by the
 * production platform asset packages: non-string preset variables (floats,
 * booleans, null), templated `add` assets, nested dot-directories, custom
 * YAML tags, the `op.new_file` merge context and conditionally-empty
 * templates.
 */
class PlatformScaffoldTest extends TestCase {

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
   * Scaffolds the sut-platform fixture project and returns the SUT dir and IO.
   */
  protected function scaffoldPlatformProject(?callable $prepare = NULL, array $user_inputs = [], bool $init = FALSE) {
    $sut = $this->fixtures->cloneFixtureProject('sut-platform');
    if ($prepare !== NULL) {
      $prepare($sut);
    }
    $io = $this->fixtures->io($user_inputs);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets-platform', 'iqual/test-assets-platform');
    $this->fixtures->runScaffold($composer, $io, $init);
    return [$sut, $io];
  }

  /**
   * Tests a scaffold run with production-shaped preset variables.
   */
  public function testScaffoldWithProductionShapedVariables() {
    [$sut, $io] = $this->scaffoldPlatformProject();
    $output = $io->getOutput();

    $this->assertStringContainsString('All required variables found.', $output);
    $this->assertStringContainsString('All assets have been scaffolded.', $output);

    // A templated 'add' asset below the @app-root token renders float and
    // boolean preset variables of the package.
    $expected_settings = <<<'PHP'
<?php

// Local settings for platform-project.
$settings['php_version'] = '8.3';
$settings['managed_updates'] = TRUE;

PHP;
    $this->assertSame($expected_settings, file_get_contents($sut . '/app/local.settings.php'));

    // An 'add' template rendering empty does not create the destination.
    $this->assertFileDoesNotExist($sut . '/.platform/routes.yaml');

    // A 'replace' template covering the twig constructs used in production:
    // the `replace` and `default` filters (with an undefined key and a
    // concatenated fallback), the `existing_file` test on a concatenated
    // location path and a `verbatim` block protecting `${{ }}` syntax.
    $expected_agents = <<<'MD'
# platform_project

Runner: ubuntu-latest
Fallback: platform-project-agents
Web root contains an index file.

Uses ${{ github.ref }} in workflows.


MD;
    $this->assertSame($expected_agents, file_get_contents($sut . '/AGENTS.md'));

    // A 'replace' template in a nested dot-directory rendering empty
    // (solr_version is null) deletes the existing destination.
    $this->assertFileDoesNotExist($sut . '/.ddev/config.solr.yaml');

    // A 'merge' template rendering empty (ai.claude is false) is skipped
    // and does not create the destination.
    $this->assertFileDoesNotExist($sut . '/.claude/settings.json');

    // A line-based merge with multi-column lines replaces whole lines by
    // their first column and appends new ones after the original's trailing
    // newline, ending with a single trailing newline.
    $this->assertSame("*.css text eol=lf\n/vendor/ export-ignore\n*.svg -diff\n", file_get_contents($sut . '/.gitattributes'));

    // A 'merge' template into a new file uses the `op.new_file` branch and
    // keeps the custom YAML tag with a scalar value.
    $services = file_get_contents($sut . '/.platform/services.yaml');
    $this->assertStringContainsString('# Platform services for platform-project.', $services);
    $this->assertStringContainsString('!archive', $services);
    $this->assertStringContainsString('mariadb:10.6', $services);
  }

  /**
   * Tests merging into an existing YAML file with a custom scalar tag.
   */
  public function testMergeUpdatesExistingTaggedYaml() {
    $existing_services = <<<YAML
# Platform services for platform-project.
search:
    type: solr:9.2
    configuration:
        cores:
            main:
                conf_dir: !archive "../solr/conf"
db:
    type: mariadb:10.5

YAML;
    [$sut, $io] = $this->scaffoldPlatformProject(function (string $sut) use ($existing_services) {
      mkdir($sut . '/.platform');
      file_put_contents($sut . '/.platform/services.yaml', $existing_services);
    });

    // The changed merge re-dumps the whole document: the managed value is
    // updated while the comment and the tagged scalar of the unmanaged
    // section survive the round-trip.
    $services = file_get_contents($sut . '/.platform/services.yaml');
    $this->assertStringContainsString('mariadb:10.6', $services);
    $this->assertStringNotContainsString('mariadb:10.5', $services);
    $this->assertStringContainsString('# Platform services for platform-project.', $services);
    $this->assertStringContainsString('!archive', $services);
    $this->assertStringContainsString('solr:9.2', $services);
    $this->assertStringNotContainsString('COMMENT_PLACEHOLDER', $services);
  }

  /**
   * Tests that a second scaffold run does not change any files.
   */
  public function testScaffoldIsIdempotent() {
    [$sut, $io] = $this->scaffoldPlatformProject();

    $files = [
      'app/local.settings.php',
      'AGENTS.md',
      '.gitattributes',
      '.platform/services.yaml',
    ];
    $contents = [];
    foreach ($files as $file) {
      $contents[$file] = file_get_contents($sut . '/' . $file);
    }

    $second_io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($second_io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets-platform', 'iqual/test-assets-platform');
    $this->fixtures->runScaffold($composer, $second_io);

    foreach ($contents as $file => $original) {
      $this->assertSame($original, file_get_contents($sut . '/' . $file), "File {$file} changed on the second scaffold run.");
    }
    // The deleted and skipped destinations stay absent.
    $this->assertFileDoesNotExist($sut . '/.ddev/config.solr.yaml');
    $this->assertFileDoesNotExist($sut . '/.claude/settings.json');
  }

  /**
   * Tests that a boolean root variable toggles a conditional merge asset.
   */
  public function testBooleanVariableTogglesMergeOutput() {
    [$sut, $io] = $this->scaffoldPlatformProject(function (string $sut) {
      $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
      $definition['extra']['project-scaffold']['ai'] = ['claude' => TRUE];
      file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    });

    // With the root overriding the package's `ai.claude` preset to true,
    // the merge template renders and creates the nested destination.
    $expected_settings = <<<JSON
{
    "permissions": {
        "defaultMode": "acceptEdits"
    },
    "project": "platform-project"
}

JSON;
    $this->assertSame($expected_settings, file_get_contents($sut . '/.claude/settings.json'));
  }

  /**
   * Tests that question defaults interpolate previously answered variables.
   */
  public function testCrossQuestionDefaultInterpolation() {
    // Answers: project name, URL (empty = interpolated default).
    [$sut, $io] = $this->scaffoldPlatformProject(NULL, ['platform-proj', ''], TRUE);
    $output = $io->getOutput();

    $this->assertStringContainsString('What is the project URL?', $output);

    // The default of the URL question interpolates the name answered in
    // the same run and is written back to the composer.json file.
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $extra = $definition['extra']['project-scaffold'];
    $this->assertSame('platform-proj', $extra['name']);
    $this->assertSame('https://platform-proj.example.com', $extra['url']);
  }

}
