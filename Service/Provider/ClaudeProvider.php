<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service\Provider;

use Angeo\AiDescriptionUpdater\Api\AiProviderInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

/**
 * Anthropic Claude Messages API provider.
 *
 * Docs: https://docs.anthropic.com/en/api/messages
 * Auth: x-api-key header (no Bearer prefix)
 * Extended thinking: requires temperature = 1 per Anthropic docs.
 */
class ClaudeProvider implements AiProviderInterface
{
    private const URL     = 'https://api.anthropic.com/v1/messages';
    private const VERSION = '2023-06-01';

    public function __construct(private readonly Config $config) {}

    public function getProviderId(): string    { return 'claude'; }
    public function getProviderLabel(): string { return 'Claude (' . $this->config->getClaudeModel() . ')'; }

    public function isConfigured(): bool
    {
        return $this->config->getClaudeApiKey() !== '';
    }

    /**
     * @throws \RuntimeException
     */
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $payload = [
            'model'      => $this->config->getClaudeModel(),
            'max_tokens' => $this->config->getClaudeMaxTokens(),
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($this->config->isClaudeExtendedThinkingEnabled()) {
            $payload['thinking']     = [
                'type'          => 'enabled',
                'budget_tokens' => $this->config->getClaudeThinkingBudget(),
            ];
            $payload['temperature'] = 1;
        }

        $data = $this->post(self::URL, $payload, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->config->getClaudeApiKey(),
            'anthropic-version: ' . self::VERSION,
        ], $this->config->getClaudeTimeout());

        // Anthropic response may contain 'thinking' + 'text' blocks; extract text only
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return trim($block['text']);
            }
        }

        throw new \RuntimeException('Claude returned no text block in response.');
    }

    // ── Private ─────────────────────────────────────────────────────────────

    private function post(string $url, array $payload, array $headers, int $timeout): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("Claude cURL error: {$err}");
        }

        $decoded = json_decode((string) $body, true) ?? [];

        if ($code !== 200) {
            $msg = $decoded['error']['message'] ?? $decoded['error']['type'] ?? mb_substr((string) $body, 0, 200);
            throw new \RuntimeException("Claude API [{$code}]: {$msg}");
        }

        return $decoded;
    }
}
