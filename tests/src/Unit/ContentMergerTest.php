<?php

namespace iqual\Composer\ProjectScaffold\Tests\Unit;

use iqual\Composer\ProjectScaffold\Util\ContentMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the content merging of the merge operation.
 */
#[CoversClass(ContentMerger::class)]
class ContentMergerTest extends TestCase {

  /**
   * Tests the merge type detection from file paths.
   */
  #[DataProvider('mergeTypeProvider')]
  public function testDetectMergeType(string $file_path, string $expected) {
    $this->assertSame($expected, ContentMerger::detectMergeType($file_path));
  }

  /**
   * Provides file paths and their expected merge types.
   */
  public static function mergeTypeProvider(): array {
    return [
      ['.env', ContentMerger::MERGE_TYPE_ENV],
      ['.env.twig', ContentMerger::MERGE_TYPE_ENV],
      ['.env.secrets', ContentMerger::MERGE_TYPE_ENV],
      ['.gitignore', ContentMerger::MERGE_TYPE_LINE],
      ['.gitignore.twig', ContentMerger::MERGE_TYPE_LINE],
      ['.dockerignore', ContentMerger::MERGE_TYPE_LINE],
      ['.gitattributes', ContentMerger::MERGE_TYPE_LINE],
      ['config.json', ContentMerger::MERGE_TYPE_JSON],
      ['config.json.twig', ContentMerger::MERGE_TYPE_JSON],
      ['services.yml', ContentMerger::MERGE_TYPE_YAML],
      ['services.yaml.twig', ContentMerger::MERGE_TYPE_YAML],
    ];
  }

  /**
   * Tests that unknown file types are rejected.
   */
  public function testDetectMergeTypeUnknownThrows() {
    $this->expectException(\RuntimeException::class);
    ContentMerger::detectMergeType('unknown.txt');
  }

  /**
   * Tests that an empty merge source returns the original contents.
   */
  public function testEmptyMergeContentsReturnsOriginal() {
    $original = "APP_NAME=old\nDEBUG=true";
    $this->assertSame($original, ContentMerger::mergeContents($original, '', ContentMerger::MERGE_TYPE_ENV));
  }

  /**
   * Tests merging of environment files.
   */
  public function testMergeEnv() {
    $original = "APP_NAME=old\nDEBUG=true";
    $merge = "APP_NAME=new\nPORT=80";
    $expected = "APP_NAME=new\nDEBUG=true\nPORT=80";
    $this->assertSame($expected, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_ENV));
  }

  /**
   * Tests that the EOL style of the original environment file is kept.
   */
  public function testMergeEnvKeepsCrlf() {
    $original = "APP_NAME=old\r\nDEBUG=true";
    $merge = "APP_NAME=new";
    $expected = "APP_NAME=new\r\nDEBUG=true";
    $this->assertSame($expected, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_ENV));
  }

  /**
   * Tests merging of line-based files.
   */
  public function testMergeLine() {
    $original = "/vendor/\n*.log";
    $merge = "/vendor/\n/node_modules/";
    $expected = "/vendor/\n*.log\n/node_modules/";
    $this->assertSame($expected, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_LINE));
  }

  /**
   * Tests merging of JSON files preserving the original indentation.
   */
  public function testMergeJson() {
    $original = <<<JSON
{
  "name": "keep",
  "database": {
    "host": "localhost"
  }
}
JSON;
    $merge = '{"database": {"host": "db", "port": 3306}}';
    $expected = <<<JSON
{
  "name": "keep",
  "database": {
    "host": "db",
    "port": 3306
  }
}
JSON;
    $this->assertSame($expected, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_JSON));
  }

  /**
   * Tests that identical JSON contents are returned unchanged.
   */
  public function testMergeJsonUnchangedReturnsOriginal() {
    $original = "{\n  \"name\": \"keep\"\n}";
    $merge = '{"name": "keep"}';
    $this->assertSame($original, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_JSON));
  }

  /**
   * Tests that invalid JSON is rejected.
   */
  public function testMergeInvalidJsonThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Could not parse JSON file.');
    ContentMerger::mergeContents('{invalid', '{"a": 1}', ContentMerger::MERGE_TYPE_JSON);
  }

  /**
   * Tests merging of YAML files preserving comments.
   */
  public function testMergeYamlPreservesComments() {
    $original = <<<YAML
# Service definitions for the project.
services:
  app:
    # The container image to use.
    image: 'php:7.4'
    restart: always

YAML;
    $merge = <<<YAML
services:
  app:
    image: 'php:8.2'

YAML;
    $result = ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_YAML);
    $this->assertStringContainsString('# Service definitions for the project.', $result);
    $this->assertStringContainsString('# The container image to use.', $result);
    $this->assertStringContainsString("image: 'php:8.2'", $result);
    $this->assertStringContainsString('restart: always', $result);
    $this->assertStringNotContainsString('COMMENT_PLACEHOLDER', $result);
  }

  /**
   * Tests that comments with single quotes survive the merge round-trip.
   */
  public function testMergeYamlPreservesSingleQuotedComments() {
    $original = <<<YAML
# This comment contains 'single quotes' in it.
key: value

YAML;
    $merge = "key: changed\n";
    $result = ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_YAML);
    $this->assertStringContainsString("# This comment contains 'single quotes' in it.", $result);
    $this->assertStringContainsString('key: changed', $result);
  }

  /**
   * Tests that identical YAML contents are returned unchanged.
   */
  public function testMergeYamlUnchangedReturnsOriginal() {
    $original = <<<YAML
# A comment that must survive.
services:
  app:
    image: 'php:8.2'

YAML;
    $merge = <<<YAML
services:
  app:
    image: 'php:8.2'

YAML;
    $this->assertSame($original, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_YAML));
  }

  /**
   * Tests that YAML custom tags are supported and compared correctly.
   */
  public function testMergeYamlWithCustomTags() {
    $original = <<<YAML
secret: !vault
  key: value

YAML;
    $merge = <<<YAML
secret: !vault
  key: value

YAML;
    $this->assertSame($original, ContentMerger::mergeContents($original, $merge, ContentMerger::MERGE_TYPE_YAML));
  }

  /**
   * Tests that invalid YAML is rejected.
   */
  public function testMergeInvalidYamlThrows() {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Could not parse YAML file.');
    ContentMerger::mergeContents("key: [\n", "key: value\n", ContentMerger::MERGE_TYPE_YAML);
  }

}
