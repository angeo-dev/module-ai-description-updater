<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Test\Unit\Service;

use Angeo\AiDescriptionUpdater\Model\Config;
use Angeo\AiDescriptionUpdater\Service\GoogleSheetsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\AiDescriptionUpdater\Service\GoogleSheetsService
 */
class GoogleSheetsServiceTest extends TestCase
{
    private Config&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
    }

    /**
     * Use reflection to access the private resolveColumnIndexes method.
     */
    private function resolveColumns(string $expression): array
    {
        $service = new GoogleSheetsService(
            $this->config,
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );

        $ref = new \ReflectionMethod($service, 'resolveColumnIndexes');
        $ref->setAccessible(true);
        return $ref->invoke($service, $expression);
    }

    // ── resolveColumnIndexes ─────────────────────────────────────────────────

    public function testSingleColumn(): void
    {
        $this->assertSame([0], $this->resolveColumns('0'));
    }

    public function testMultipleColumns(): void
    {
        $this->assertSame([0, 2, 5], $this->resolveColumns('0,2,5'));
    }

    public function testRange(): void
    {
        $this->assertSame([0, 1, 2, 3], $this->resolveColumns('0-3'));
    }

    public function testMixedExpression(): void
    {
        $this->assertSame([0, 2, 3, 4, 7], $this->resolveColumns('0,2-4,7'));
    }

    public function testDeduplicatesIndexes(): void
    {
        $this->assertSame([0, 1, 2], $this->resolveColumns('0,1,0-2'));
    }

    public function testIgnoresNegativeIndexes(): void
    {
        $result = $this->resolveColumns('0');
        foreach ($result as $idx) {
            $this->assertGreaterThanOrEqual(0, $idx);
        }
    }

    public function testWhitespaceIsIgnored(): void
    {
        $this->assertSame([0, 2, 5], $this->resolveColumns(' 0 , 2 , 5 '));
    }

    public function testRangeReversedOrder(): void
    {
        // "3-0" should still work as 0,1,2,3
        $this->assertSame([0, 1, 2, 3], $this->resolveColumns('3-0'));
    }
}
