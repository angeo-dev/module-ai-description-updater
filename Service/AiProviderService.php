<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Angeo\AiDescriptionUpdater\Api\AiProviderInterface;
use Angeo\AiDescriptionUpdater\Model\Config;
use Angeo\AiDescriptionUpdater\Model\AttributeConfig;
use Angeo\AiDescriptionUpdater\Service\Security\HtmlSanitizer;
use Magento\Store\Model\ScopeInterface;

/**
 * Selects the configured AI provider and delegates generation to it.
 * Providers are injected via di.xml — adding a new provider requires
 * only a new AiProviderInterface implementation + di.xml entry.
 */
class AiProviderService
{
    /** @param AiProviderInterface[] $providers keyed by provider ID */
    public function __construct(
        private readonly Config        $config,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly array         $providers = [],
    ) {}

    /**
     * Generate content for a single AttributeConfig.
     *
     * @throws \RuntimeException when provider is not configured or API fails
     */
    public function generateForAttribute(
        AttributeConfig $attrConfig,
        string $productName,
        string $productSku,
        string $storeName = '',
        ?int $storeId = null
    ): string {
        $provider = $this->resolveProvider();
        $prompt   = $this->config->buildPromptForAttribute(
            $attrConfig,
            $productName,
            $productSku,
            $storeName,
            $storeId
        );
        $raw      = $provider->generate($this->config->getSystemRole(ScopeInterface::SCOPE_STORE, $storeId), $prompt);

        return $this->postProcess($raw, $attrConfig);
    }

    /**
     * Generate all enabled attributes for one product.
     *
     * @return array<string, string>  ['attribute_code' => 'value', ...]
     * @throws \RuntimeException
     */
    public function generateAllAttributes(
        string $productName,
        string $productSku,
        string $storeName = '',
        ?int $storeId = null
    ): array {
        $results = [];
        foreach ($this->config->getEnabledAttributes(ScopeInterface::SCOPE_STORE, $storeId) as $attrConfig) {
            $results[$attrConfig->attributeCode] = $this->generateForAttribute(
                $attrConfig,
                $productName,
                $productSku,
                $storeName,
                $storeId
            );
        }
        return $results;
    }

    public function getActiveProviderLabel(): string
    {
        $provider = $this->resolveProvider();
        return $provider->getProviderLabel();
    }

    // ── Private ─────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException when configured provider is missing or not configured
     */
    private function resolveProvider(): AiProviderInterface
    {
        $id       = $this->config->getAiProvider();
        $provider = $this->providers[$id] ?? null;

        if ($provider === null) {
            throw new \RuntimeException("AI provider '{$id}' is not registered. Check di.xml.");
        }

        if (!$provider->isConfigured()) {
            throw new \RuntimeException(
                "AI provider '{$provider->getProviderLabel()}' is not configured. Please add an API key in Settings."
            );
        }

        return $provider;
    }

    private function postProcess(string $raw, AttributeConfig $attrConfig): string
    {
        $value = trim($raw);

        if ($attrConfig->isHtml) {
            // Untrusted model output rendered as HTML on the storefront —
            // run through the whitelist sanitizer to prevent stored XSS.
            $value = $this->htmlSanitizer->sanitize($value);
        } else {
            $value = strip_tags($value);
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = trim($value);
        }

        if ($attrConfig->maxLength > 0 && mb_strlen($value) > $attrConfig->maxLength) {
            $value     = mb_substr($value, 0, $attrConfig->maxLength);
            $lastSpace = mb_strrpos($value, ' ');
            if ($lastSpace > $attrConfig->maxLength * 0.7) {
                $value = mb_substr($value, 0, $lastSpace);
            }
            $value = rtrim($value, '.,;: ');
        }

        return $value;
    }
}
