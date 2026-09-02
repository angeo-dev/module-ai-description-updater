<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service\Security;

/**
 * Neutralises CSV / spreadsheet formula injection.
 *
 * Values starting with =, +, -, @ (or containing leading tab / CR) are
 * interpreted as formulas by Excel, LibreOffice and Google Sheets. Product
 * names and AI-generated text are untrusted, so every cell written to a CSV
 * file must be escaped. (The Google Sheets export uses valueInputOption=RAW,
 * which already prevents formula evaluation server-side.)
 */
class SpreadsheetValueSanitizer
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Escape a single cell value for safe CSV output.
     */
    public function escapeCell(string $value): string
    {
        // Strip control characters that can confuse parsers.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        if ($value === '') {
            return $value;
        }

        if (in_array($value[0], self::DANGEROUS_PREFIXES, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Escape every cell in a row.
     *
     * @param array<int|string, mixed> $row
     * @return array<int|string, string>
     */
    public function escapeRow(array $row): array
    {
        return array_map(fn($cell) => $this->escapeCell((string) $cell), $row);
    }
}
