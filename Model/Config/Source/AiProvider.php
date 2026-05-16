<?php
declare(strict_types=1);
namespace Angeo\AiDescriptionUpdater\Model\Config\Source;
use Magento\Framework\Data\OptionSourceInterface;
class AiProvider implements OptionSourceInterface {
    public const OPENAI = 'openai';
    public const CLAUDE = 'claude';
    public const GEMINI = 'gemini';
    public const GROQ   = 'groq';
    public function toOptionArray(): array {
        return [
            ['value' => self::OPENAI, 'label' => 'OpenAI (ChatGPT)'],
            ['value' => self::CLAUDE, 'label' => 'Anthropic (Claude)'],
            ['value' => self::GEMINI, 'label' => 'Google (Gemini)'],
            ['value' => self::GROQ,   'label' => 'Groq (Free — Llama / Mixtral)'],
        ];
    }
}
