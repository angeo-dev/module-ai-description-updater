<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service\Provider;

use Angeo\AiDescriptionUpdater\Api\AiProviderInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

class OpenAiProvider implements AiProviderInterface
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private readonly Config $config) {}

    public function getProviderId(): string    { return 'openai'; }
    public function getProviderLabel(): string { return 'OpenAI (' . $this->config->getOpenAiModel() . ')'; }

    public function isConfigured(): bool
    {
        return $this->config->getOpenAiApiKey() !== '';
    }

    /**
     * @throws \RuntimeException
     */
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $payload = [
            'model'       => $this->config->getOpenAiModel(),
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'temperature' => $this->config->getOpenAiTemperature(),
            'max_tokens'  => $this->config->getOpenAiMaxTokens(),
        ];

        $data = $this->post(self::URL, $payload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->getOpenAiApiKey(),
        ], $this->config->getOpenAiTimeout());

        $content = $data['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            throw new \RuntimeException('OpenAI returned an empty response.');
        }

        return trim($content);
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
            throw new \RuntimeException("OpenAI cURL error: {$err}");
        }

        $decoded = json_decode((string) $body, true) ?? [];

        if ($code !== 200) {
            $msg = $decoded['error']['message'] ?? mb_substr((string) $body, 0, 200);
            throw new \RuntimeException("OpenAI API [{$code}]: {$msg}");
        }

        return $decoded;
    }
}
