<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Language implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'en', 'label' => 'English'],
            ['value' => 'uk', 'label' => 'Ukrainian'],
            ['value' => 'de', 'label' => 'German'],
            ['value' => 'fr', 'label' => 'French'],
            ['value' => 'es', 'label' => 'Spanish'],
            ['value' => 'it', 'label' => 'Italian'],
            ['value' => 'nl', 'label' => 'Dutch'],
            ['value' => 'pl', 'label' => 'Polish'],
        ];
    }
}
