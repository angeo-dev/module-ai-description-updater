<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service\Provider;

use Angeo\AiDescriptionUpdater\Api\AiProviderInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

/**
 * Google Gemini generateContent API provider.
 *
 * Docs:     https://ai.google.dev/api/generate-content
 * Endpoint: POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 * Auth:     ?key=API_KEY query param (Google API key — not OAuth2)
 *
 * Gemini does not have a separate system-role param in the same way;
 * the system instruction is sent via the `system_instruction` field
 * (supported in Gemini 1.5+ and all Gemini 2.x models).
 */
class GeminiProvider implements AiProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(private readonly Config $config) {}

    public function getProviderId(): string    { return 'gemini'; }
    public function getProviderLabel(): string { return 'Gemini (' . $this->config->getGeminiModel() . ')'; }

    public function isConfigured(): bool
    {
        return $this->config->getGeminiApiKey() !== '';
    }

    /**
     * @throws \RuntimeException
     */
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $model = $this->config->getGeminiModel();
        $url   = sprintf('%s/%s:generateContent?key=%s', self::BASE_URL, $model, $this->config->getGeminiApiKey());

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $this->config->getGeminiTemperature(),
                'maxOutputTokens' => $this->config->getGeminiMaxTokens(),
            ],
        ];

        $data = $this->post($url, $payload, [
            'Content-Type: application/json',
        ], $this->config->getGeminiTimeout());

        // Gemini response structure: candidates[0].content.parts[0].text
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if ($text === '') {
            // Check for safety/finish reason
            $reason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
            throw new \RuntimeException("Gemini returned empty response. finishReason: {$reason}");
        }

        return trim($text);
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
            throw new \RuntimeException("Gemini cURL error: {$err}");
        }

        $decoded = json_decode((string) $body, true) ?? [];

        if ($code !== 200) {
            $msg = $decoded['error']['message'] ?? mb_substr((string) $body, 0, 200);
            throw new \RuntimeException("Gemini API [{$code}]: {$msg}");
        }

        return $decoded;
    }
}
