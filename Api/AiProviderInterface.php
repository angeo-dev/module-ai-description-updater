<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Api;

/**
 * Contract for every AI provider.
 * Follows the AeoBrandVisibility AiProviderInterface pattern.
 */
interface AiProviderInterface
{
    /**
     * Generate content for the given prompt.
     *
     * @throws \RuntimeException on API error, auth failure, or timeout
     */
    public function generate(string $systemPrompt, string $userPrompt): string;

    public function getProviderId(): string;

    public function getProviderLabel(): string;

    /** Returns true only when API key and required config are non-empty. */
    public function isConfigured(): bool;
}
