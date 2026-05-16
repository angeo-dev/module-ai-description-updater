<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Model;

/**
 * Immutable value object carrying generation settings for a single product attribute.
 */
final class AttributeConfig
{
    public function __construct(
        public readonly string $attributeCode,
        public readonly string $promptOverride = '',
        public readonly int    $maxLength = 0,
        public readonly bool   $isHtml = false,
    ) {}
}
