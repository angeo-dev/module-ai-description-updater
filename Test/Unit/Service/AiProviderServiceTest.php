<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Test\Unit\Service;

use Angeo\AiDescriptionUpdater\Api\AiProviderInterface;
use Angeo\AiDescriptionUpdater\Model\AttributeConfig;
use Angeo\AiDescriptionUpdater\Model\Config;
use Angeo\AiDescriptionUpdater\Service\AiProviderService;
use Angeo\AiDescriptionUpdater\Service\Security\HtmlSanitizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\AiDescriptionUpdater\Service\AiProviderService
 */
class AiProviderServiceTest extends TestCase
{
    private Config&MockObject $config;
    private AiProviderInterface&MockObject $providerA;
    private AiProviderInterface&MockObject $providerB;
    private HtmlSanitizer $htmlSanitizer;

    protected function setUp(): void
    {
        $this->config    = $this->createMock(Config::class);
        $this->providerA = $this->createMock(AiProviderInterface::class);
        $this->providerB = $this->createMock(AiProviderInterface::class);
        $this->htmlSanitizer = new HtmlSanitizer();
    }

    // ── resolveProvider ──────────────────────────────────────────────────────

    public function testThrowsWhenProviderNotRegistered(): void
    {
        $this->config->method('getAiProvider')->willReturn('unknown');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, ['openai' => $this->providerA]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/provider 'unknown' is not registered/i");

        $service->getActiveProviderLabel();
    }

    public function testThrowsWhenProviderNotConfigured(): void
    {
        $this->config->method('getAiProvider')->willReturn('groq');
        $this->providerA->method('isConfigured')->willReturn(false);
        $this->providerA->method('getProviderLabel')->willReturn('Groq (llama)');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, ['groq' => $this->providerA]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/not configured/i");

        $service->getActiveProviderLabel();
    }

    public function testReturnsActiveProviderLabel(): void
    {
        $this->config->method('getAiProvider')->willReturn('groq');
        $this->providerA->method('isConfigured')->willReturn(true);
        $this->providerA->method('getProviderLabel')->willReturn('Groq (llama-3.3-70b-versatile)');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, ['groq' => $this->providerA]);

        $this->assertSame('Groq (llama-3.3-70b-versatile)', $service->getActiveProviderLabel());
    }

    // ── generateAllAttributes ────────────────────────────────────────────────

    public function testGenerateAllAttributesCallsProviderForEachAttribute(): void
    {
        $attrDesc  = new AttributeConfig('description', '', 0, true);
        $attrShort = new AttributeConfig('short_description', '', 150, false);

        $this->config->method('getAiProvider')->willReturn('openai');
        $this->config->method('getEnabledAttributes')->willReturn([$attrDesc, $attrShort]);
        $this->config->method('getSystemRole')->willReturn('You are a copywriter.');
        $this->config->method('buildPromptForAttribute')->willReturnCallback(
            fn($attr, $name, $sku, $store) => "Prompt for {$attr->attributeCode}"
        );

        $this->providerA->method('isConfigured')->willReturn(true);
        $this->providerA->method('getProviderLabel')->willReturn('OpenAI (gpt-4.1)');
        $this->providerA->expects($this->exactly(2))
            ->method('generate')
            ->willReturn('<p>Generated content.</p>');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, ['openai' => $this->providerA]);

        $result = $service->generateAllAttributes('Gold Ring', 'SKU-001', 'Dutch Store');

        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('short_description', $result);
    }

    public function testPostProcessStripsHtmlForNonHtmlAttributes(): void
    {
        $attr = new AttributeConfig('meta_title', '', 0, false);

        $this->config->method('getAiProvider')->willReturn('openai');
        $this->config->method('getEnabledAttributes')->willReturn([$attr]);
        $this->config->method('getSystemRole')->willReturn('');
        $this->config->method('buildPromptForAttribute')->willReturn('prompt');

        $this->providerA->method('isConfigured')->willReturn(true);
        $this->providerA->method('getProviderLabel')->willReturn('OpenAI');
        $this->providerA->method('generate')->willReturn('<strong>Gold Ring 18k</strong>');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, ['openai' => $this->providerA]);
        $result  = $service->generateAllAttributes('Gold Ring', 'SKU-001');

        $this->assertSame('Gold Ring 18k', $result['meta_title']);
    }

    public function testPostProcessTruncatesAtMaxLength(): void
    {
        $attr = new AttributeConfig('short_description', '', 20, false);

        $this->config->method('getAiProvider')->willReturn('openai');
        $this->config->method('getEnabledAttributes')->willReturn([$attr]);
        $this->config->method('getSystemRole')->willReturn('');
        $this->config->method('buildPromptForAttribute')->willReturn('prompt');

        $this->providerA->method('isConfigured')->willReturn(true);
        $this->providerA->method('getProviderLabel')->willReturn('OpenAI');
        $this->providerA->method('generate')->willReturn('This is a very long text that exceeds the limit');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, ['openai' => $this->providerA]);
        $result  = $service->generateAllAttributes('Product', 'SKU');

        $this->assertLessThanOrEqual(20, mb_strlen($result['short_description']));
    }

    // ── provider selection with multiple providers ───────────────────────────

    public function testSelectsCorrectProviderFromRegistry(): void
    {
        $this->config->method('getAiProvider')->willReturn('claude');
        $this->config->method('getEnabledAttributes')->willReturn([]);

        $this->providerA->method('isConfigured')->willReturn(true);
        $this->providerA->method('getProviderLabel')->willReturn('OpenAI');
        $this->providerB->method('isConfigured')->willReturn(true);
        $this->providerB->method('getProviderLabel')->willReturn('Claude (claude-sonnet-4-6)');

        $service = new AiProviderService($this->config, $this->htmlSanitizer, [
            'openai' => $this->providerA,
            'claude' => $this->providerB,
        ]);

        $this->assertSame('Claude (claude-sonnet-4-6)', $service->getActiveProviderLabel());
    }
}
