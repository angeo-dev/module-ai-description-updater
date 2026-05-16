<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Test\Unit\Service\Provider;

use Angeo\AiDescriptionUpdater\Model\Config;
use Angeo\AiDescriptionUpdater\Service\Provider\GroqProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\AiDescriptionUpdater\Service\Provider\GroqProvider
 */
class GroqProviderTest extends TestCase
{
    private Config&MockObject $config;
    private GroqProvider $provider;

    protected function setUp(): void
    {
        $this->config   = $this->createMock(Config::class);
        $this->provider = new GroqProvider($this->config);
    }

    public function testGetProviderId(): void
    {
        $this->assertSame('groq', $this->provider->getProviderId());
    }

    public function testGetProviderLabelIncludesModel(): void
    {
        $this->config->method('getGroqModel')->willReturn('llama-3.3-70b-versatile');
        $label = $this->provider->getProviderLabel();
        $this->assertStringContainsString('llama-3.3-70b-versatile', $label);
    }

    public function testIsConfiguredWhenApiKeyPresent(): void
    {
        $this->config->method('getGroqApiKey')->willReturn('gsk_test123');
        $this->assertTrue($this->provider->isConfigured());
    }

    public function testIsNotConfiguredWhenApiKeyEmpty(): void
    {
        $this->config->method('getGroqApiKey')->willReturn('');
        $this->assertFalse($this->provider->isConfigured());
    }
}
