<?php

namespace iqual\Composer\ProjectScaffold\Tests\Integration;

use Composer\Package\Locker;
use iqual\Composer\ProjectScaffold\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Tests the interactive questions logic with scripted user input.
 */
class InteractiveQuestionsTest extends TestCase {

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
   * Reads the scaffold options of a project's composer.json file.
   */
  protected function readScaffoldExtra(string $sut) {
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    return $definition['extra']['project-scaffold'];
  }

  /**
   * Tests that missing required variables fail when not interactive.
   */
  public function testMissingRequiredVariablesThrowWhenNotInteractive() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Required values are missing.');
    $this->fixtures->runScaffold($composer, $io);
  }

  /**
   * Tests that unanswerable questions fail a non-interactive run.
   *
   * When the init operation runs non-interactively, a question without a
   * default value and without a pre-existing value in the root
   * composer.json must fail the run naming the variable, instead of
   * silently writing an empty answer.
   */
  public function testMissingDefaultsFailWhenNotInteractive() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    $composer_json = file_get_contents($sut . '/composer.json');
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');

    try {
      $this->fixtures->runScaffold($composer, $io, TRUE);
      $this->fail('The non-interactive init run should have failed.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('Cannot answer the questions of iqual/test-assets in non-interactive mode: no value for "deployment". Add a default to the question, set the variable in the \'extra.project-scaffold\' section of the root composer.json, or run the command interactively.', $exception->getMessage());
    }

    // No (empty) answers were written to the composer.json file.
    $this->assertSame($composer_json, file_get_contents($sut . '/composer.json'));
  }

  /**
   * Tests that defaults and preset values are used non-interactively.
   */
  public function testDefaultsAreUsedWhenNotInteractive() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    // Preset the only variable that has neither a default nor a value.
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['deployment'] = 'none';
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io, TRUE);

    // The name question falls back to its (filtered) default, the PHP
    // version comes from the asset package's preset variables and the
    // conditional cluster question is skipped.
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame('sut-questions', $extra['name']);
    $this->assertSame('8.2', $extra['runtime']['php_version']);
    $this->assertSame('none', $extra['deployment']);
    $this->assertArrayNotHasKey('cluster', $extra);
    $this->assertSame("# sut-questions\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that missing required variables are prompted interactively.
   */
  public function testQuestionsAreAskedForMissingVariables() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    // Answers: project name, PHP version (option 1 = 8.3), deployment
    // (option 1 = kubernetes), cluster domain (empty = default).
    $io = $this->fixtures->io(['my-project', '1', '1', '']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    $this->assertStringContainsString('Did not find all required variables.', $output);
    $this->assertStringContainsString('Configure iqual/test-assets:', $output);
    $this->assertStringContainsString('What is the code name of the project?', $output);
    $this->assertStringContainsString('What PHP version should be installed?', $output);
    $this->assertStringContainsString('What deployment strategy is used?', $output);
    $this->assertStringContainsString('What is the cluster domain?', $output);

    // The answers are used for the scaffolded files.
    $this->assertSame("# my-project\n\nProject running on PHP 8.3.\n", file_get_contents($sut . '/README.md'));

    // The answers are written to the composer.json file.
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame('my-project', $extra['name']);
    $this->assertSame('8.3', $extra['runtime']['php_version']);
    $this->assertSame('kubernetes', $extra['deployment']);
    $this->assertSame('k8s.example.com', $extra['cluster']);
    $this->assertStringContainsString("Writing variables to 'extra' section of top-level composer.json file.", $output);

    // The project has no composer.lock file, so the lock file update is
    // skipped without any output.
    $this->assertStringNotContainsString('Updating the hash in the composer.lock file.', $output);
    $this->assertStringNotContainsString('Cannot update lock file', $output);
  }

  /**
   * Tests that the write-back only refreshes the hash of the lock file.
   */
  public function testWriteBackRefreshesLockFileHash() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    $lock = [
      'content-hash' => Locker::getContentHash(file_get_contents($sut . '/composer.json')),
      'packages' => [
        ['name' => 'iqual/test-assets', 'version' => '1.0.0', 'type' => 'library'],
      ],
      'packages-dev' => [],
      'aliases' => [],
      'minimum-stability' => 'stable',
      'stability-flags' => new \stdClass(),
      'prefer-stable' => FALSE,
      'prefer-lowest' => FALSE,
      'platform' => new \stdClass(),
      'platform-dev' => new \stdClass(),
      'plugin-api-version' => '2.9.0',
    ];
    file_put_contents($sut . '/composer.lock', json_encode($lock, JSON_PRETTY_PRINT));
    $lock_mtime = time() - 3600;
    touch($sut . '/composer.lock', $lock_mtime);

    // Answers: project name, PHP version (empty = default 8.2), deployment
    // (option 0 = none).
    $io = $this->fixtures->io(['my-project', '', '0']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    $this->assertStringContainsString("Writing variables to 'extra' section of top-level composer.json file.", $output);
    $this->assertStringContainsString('Updating the hash in the composer.lock file.', $output);
    // No nested `composer update --lock` was run.
    $this->assertStringNotContainsString('Loading composer repositories', $output);

    // The content hash matches the updated composer.json file, everything
    // else (including the modification time) is kept.
    $updated_lock = json_decode(file_get_contents($sut . '/composer.lock'), TRUE);
    $this->assertSame('my-project', $this->readScaffoldExtra($sut)['name']);
    $this->assertSame(Locker::getContentHash(file_get_contents($sut . '/composer.json')), $updated_lock['content-hash']);
    $this->assertSame($lock['packages'], $updated_lock['packages']);
    $this->assertSame($lock['packages-dev'], $updated_lock['packages-dev']);
    clearstatcache();
    $this->assertSame($lock_mtime, filemtime($sut . '/composer.lock'));
  }

  /**
   * Tests that conditional questions are skipped if the condition fails.
   */
  public function testConditionalQuestionIsSkipped() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    // Answers: project name (empty = filtered default), PHP version
    // (empty = default 8.2), deployment (option 0 = none). The cluster
    // question must not be asked.
    $io = $this->fixtures->io(['', '', '0']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    $this->assertStringNotContainsString('What is the cluster domain?', $output);

    // The default of the name question is the root package name with the
    // 'fixtures/' prefix removed by the question's filter.
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame('sut-questions', $extra['name']);
    $this->assertSame('8.2', $extra['runtime']['php_version']);
    $this->assertSame('none', $extra['deployment']);
    $this->assertArrayNotHasKey('cluster', $extra);
  }

  /**
   * Tests that invalid answers are rejected and asked again.
   */
  public function testValidationRejectsInvalidAnswers() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    // The first answer violates the validation pattern of the name
    // question and is asked again.
    $io = $this->fixtures->io(['Invalid Name!', 'valid-name', '', '0']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    $this->assertStringContainsString('The input does not match the required format:', $output);
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame('valid-name', $extra['name']);
  }

  /**
   * Tests that a multiple choice answer survives the full round-trip.
   */
  public function testMultipleChoiceQuestionRoundTrip() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    // Enable the conditional languages question.
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['multilingual'] = 'yes';
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Answers: project name, PHP version (empty = default 8.2),
    // deployment (option 0 = none), languages (options 1 and 3 = fr and
    // en), default language (empty = default, asked because the answer
    // contains fr).
    $io = $this->fixtures->io(['my-project', '', '0', '1,3', '']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);
    $output = $io->getOutput();

    $this->assertStringContainsString('Which languages are enabled?', $output);
    $this->assertStringContainsString('What is the default language?', $output);

    // The answer is written to the composer.json file as a list and
    // rendered as a list in the templates.
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame(['fr', 'en'], $extra['languages']);
    $this->assertSame('fr', $extra['default_language']);
    $readme = "# my-project\n\nProject running on PHP 8.2.\nLanguages: fr, en.\n";
    $this->assertSame($readme, file_get_contents($sut . '/README.md'));

    // A second, non-interactive run re-reads the stored answer without
    // clobbering it.
    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io);

    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame(['fr', 'en'], $extra['languages']);
    $this->assertSame($readme, file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that a multiple choice default is used non-interactively.
   */
  public function testMultipleChoiceDefaultIsUsedWhenNotInteractive() {
    $sut = $this->fixtures->cloneFixtureProject('sut-questions');
    // Enable the conditional languages question and preset the variable
    // that has neither a default nor a value.
    $definition = json_decode(file_get_contents($sut . '/composer.json'), TRUE);
    $definition['extra']['project-scaffold']['multilingual'] = 'yes';
    $definition['extra']['project-scaffold']['deployment'] = 'none';
    file_put_contents($sut . '/composer.json', json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $io = $this->fixtures->io();
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io, TRUE);

    // The languages question falls back to its list default and the
    // dependent default language question is skipped (no "fr" selected).
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame(['de'], $extra['languages']);
    $this->assertArrayNotHasKey('default_language', $extra);
    $this->assertStringContainsString("Languages: de.\n", file_get_contents($sut . '/README.md'));
  }

  /**
   * Tests that the init operation prompts even with preset variables.
   */
  public function testInitAlwaysPrompts() {
    $sut = $this->fixtures->cloneFixtureProject('sut-preset');
    // Keep the preset name and PHP version, choose 'none' for deployment.
    $io = $this->fixtures->io(['', '', '0']);
    $composer = $this->fixtures->createComposer($io, $sut);
    $this->fixtures->installFixturePackage($composer, $sut, 'test-assets', 'iqual/test-assets');
    $this->fixtures->runScaffold($composer, $io, TRUE);
    $output = $io->getOutput();

    $this->assertStringContainsString('Configure iqual/test-assets:', $output);
    $this->assertStringContainsString('What is the code name of the project?', $output);

    // The preset values are offered as defaults and kept.
    $extra = $this->readScaffoldExtra($sut);
    $this->assertSame('preset-project', $extra['name']);
    $this->assertSame('8.2', $extra['runtime']['php_version']);
    $this->assertSame("# preset-project\n\nProject running on PHP 8.2.\n", file_get_contents($sut . '/README.md'));
  }

}
