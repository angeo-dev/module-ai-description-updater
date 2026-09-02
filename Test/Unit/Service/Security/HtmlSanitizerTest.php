<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Test\Unit\Service\Security;

use Angeo\AiDescriptionUpdater\Service\Security\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\AiDescriptionUpdater\Service\Security\HtmlSanitizer
 */
class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testRemovesScriptTagAndContent(): void
    {
        $out = $this->sanitizer->sanitize('<p>Hello</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function testStripsEventHandlerAttributes(): void
    {
        $out = $this->sanitizer->sanitize('<p onclick="evil()">Text</p>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('Text', $out);
    }

    public function testStripsImgWithOnerror(): void
    {
        $out = $this->sanitizer->sanitize('<img src="x" onerror="alert(1)">');
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringNotContainsString('<img', $out);
    }

    public function testKeepsAllowedFormatting(): void
    {
        $out = $this->sanitizer->sanitize('<p><strong>Bold</strong> and <em>italic</em></p>');
        $this->assertStringContainsString('<strong>', $out);
        $this->assertStringContainsString('<em>', $out);
    }

    public function testDropsJavascriptHref(): void
    {
        $out = $this->sanitizer->sanitize('<a href="javascript:alert(1)">link</a>');
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringContainsString('link', $out);
    }

    public function testKeepsHttpHref(): void
    {
        $out = $this->sanitizer->sanitize('<a href="https://example.com">link</a>');
        $this->assertStringContainsString('https://example.com', $out);
    }

    public function testEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }
}
