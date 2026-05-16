<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Test\Unit\Model;

use Angeo\AiDescriptionUpdater\Model\AttributeConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\AiDescriptionUpdater\Model\AttributeConfig
 */
class AttributeConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new AttributeConfig('description');

        $this->assertSame('description', $config->attributeCode);
        $this->assertSame('', $config->promptOverride);
        $this->assertSame(0, $config->maxLength);
        $this->assertFalse($config->isHtml);
    }

    public function testCustomValues(): void
    {
        $config = new AttributeConfig(
            attributeCode:  'short_description',
            promptOverride: 'Write a short summary',
            maxLength:      150,
            isHtml:         false,
        );

        $this->assertSame('short_description', $config->attributeCode);
        $this->assertSame('Write a short summary', $config->promptOverride);
        $this->assertSame(150, $config->maxLength);
        $this->assertFalse($config->isHtml);
    }

    public function testHtmlAttribute(): void
    {
        $config = new AttributeConfig(
            attributeCode: 'description',
            isHtml:        true,
        );

        $this->assertTrue($config->isHtml);
    }

    public function testIsImmutable(): void
    {
        $config = new AttributeConfig('description', 'prompt', 100, true);

        // Value objects are readonly — PHP will throw Error on write attempt
        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $config->attributeCode = 'changed';
    }
}
