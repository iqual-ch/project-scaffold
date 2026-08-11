<?php

namespace iqual\Composer\ProjectScaffold\Tests\Unit;

use iqual\Composer\ProjectScaffold\Util\Renderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the twig template renderer.
 */
#[CoversClass(Renderer::class)]
class RendererTest extends TestCase {

  /**
   * Path to a temporary template file.
   *
   * @var string
   */
  protected $templateFile;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->templateFile && file_exists($this->templateFile)) {
      unlink($this->templateFile);
    }
  }

  /**
   * Writes a temporary template file.
   */
  protected function writeTemplate(string $contents): string {
    $this->templateFile = tempnam(sys_get_temp_dir(), 'project-scaffold-template-') . '.twig';
    file_put_contents($this->templateFile, $contents);
    return $this->templateFile;
  }

  /**
   * Tests rendering a twig template with variables.
   */
  public function testRenderTwigTemplate() {
    $file = $this->writeTemplate("Hello {{ name }} on PHP {{ runtime.php_version }}!");
    $renderer = new Renderer(['name' => 'example', 'runtime' => ['php_version' => '8.3']]);
    $this->assertSame('Hello example on PHP 8.3!', $renderer->render($file));
  }

  /**
   * Tests that extra data overrides the default data.
   */
  public function testRenderWithExtraData() {
    $file = $this->writeTemplate("{{ name }}");
    $renderer = new Renderer(['name' => 'original']);
    $this->assertSame('override', $renderer->render($file, 'twig', ['name' => 'override']));
  }

  /**
   * Tests the custom existing_file twig test.
   */
  public function testExistingFileTest() {
    $file = $this->writeTemplate("{% if file is existing_file %}exists{% else %}missing{% endif %}");
    $renderer = new Renderer(['file' => __FILE__]);
    $this->assertSame('exists', $renderer->render($file));

    $renderer->setData(['file' => '/nonexistent/path/to/file']);
    $this->assertSame('missing', $renderer->render($file));
  }

  /**
   * Tests that non-twig types return the raw file contents.
   */
  public function testRenderNonTwigTypeReturnsRawContents() {
    $file = $this->writeTemplate("Hello {{ name }}!");
    $renderer = new Renderer(['name' => 'example']);
    $this->assertSame('Hello {{ name }}!', $renderer->render($file, 'raw'));
  }

}
