<?php
declare(strict_types=1);
namespace Angeo\AiDescriptionUpdater\Service\Provider;

use Angeo\AiDescriptionUpdater\Api\AiProviderInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

/**
 * Groq AI provider — OpenAI-compatible API.
 * Free tier: 30 RPM, 14,400 RPD. No credit card required.
 * Get API key: console.groq.com
 */
class GroqProvider implements AiProviderInterface
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(private readonly Config $config) {}

    public function getProviderId(): string    { return 'groq'; }
    public function getProviderLabel(): string { return 'Groq (' . $this->config->getGroqModel() . ')'; }

    public function isConfigured(): bool
    {
        return $this->config->getGroqApiKey() !== '';
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $payload = [
            'model'       => $this->config->getGroqModel(),
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'temperature' => $this->config->getGroqTemperature(),
            'max_tokens'  => $this->config->getGroqMaxTokens(),
        ];

        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $this->config->getGroqTimeout(),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config->getGroqApiKey(),
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new \RuntimeException("Groq cURL error: {$err}");

        $decoded = json_decode((string) $body, true) ?? [];
        if ($code !== 200) {
            $msg = $decoded['error']['message'] ?? mb_substr((string) $body, 0, 200);
            throw new \RuntimeException("Groq API [{$code}]: {$msg}");
        }

        $content = $decoded['choices'][0]['message']['content'] ?? '';
        if ($content === '') throw new \RuntimeException('Groq returned empty response.');

        return trim($content);
    }
}
