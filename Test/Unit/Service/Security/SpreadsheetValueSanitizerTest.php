<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Test\Unit\Service\Security;

use Angeo\AiDescriptionUpdater\Service\Security\SpreadsheetValueSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\AiDescriptionUpdater\Service\Security\SpreadsheetValueSanitizer
 */
class SpreadsheetValueSanitizerTest extends TestCase
{
    private SpreadsheetValueSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new SpreadsheetValueSanitizer();
    }

    /**
     * @dataProvider dangerousValues
     */
    public function testPrefixesDangerousValues(string $input): void
    {
        $this->assertSame("'" . $input, $this->sanitizer->escapeCell($input));
    }

    public static function dangerousValues(): array
    {
        return [
            'equals'    => ['=1+1'],
            'plus'      => ['+1'],
            'minus'     => ['-1'],
            'at'        => ['@SUM(A1)'],
            'formula'   => ['=IMPORTXML("http://evil","//x")'],
        ];
    }

    public function testLeavesSafeValuesUnchanged(): void
    {
        $this->assertSame('Gold Ring 18k', $this->sanitizer->escapeCell('Gold Ring 18k'));
    }

    public function testStripsControlCharacters(): void
    {
        $this->assertSame('clean', $this->sanitizer->escapeCell("cl\x00ean"));
    }

    public function testEscapesEveryCellInRow(): void
    {
        $row = ['SKU-1', '=cmd', 'normal'];
        $this->assertSame(['SKU-1', "'=cmd", 'normal'], $this->sanitizer->escapeRow($row));
    }
}
