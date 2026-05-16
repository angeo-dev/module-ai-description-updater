<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

class GoogleSheetsService
{
    public function __construct(
        private readonly Config          $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetch SKUs from the configured public Google Spreadsheet (CSV export URL).
     *
     * @return string[]
     * @throws \RuntimeException
     */
    public function fetchSkus(): array
    {
        $url = $this->config->getGoogleSheetCsvUrl();
        $this->logger->info('[GoogleSheets] Fetching spreadsheet', ['url' => $url]);

        $csv  = $this->downloadCsv($url);
        $rows = $this->parseCsv($csv);

        // Remove header row
        array_shift($rows);

        $columnIndex = $this->config->getSkuColumnIndex();
        $skus        = [];

        foreach ($rows as $row) {
            $value = $row[$columnIndex] ?? '';
            if ($value !== '') {
                $skus[] = trim($value);
            }
        }

        $this->logger->info('[GoogleSheets] Fetched SKUs', ['count' => count($skus)]);
        return $skus;
    }

    // ── Private ─────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException
     */
    private function downloadCsv(string $url): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('cURL error fetching Google Sheet: ' . $curlError);
        }

        if (stripos((string) $response, '<html') !== false) {
            throw new \RuntimeException(
                'Google Sheet returned HTML instead of CSV. Check sharing settings (must be "Anyone with link can view").'
            );
        }

        return (string) $response;
    }

    private function parseCsv(string $content): array
    {
        return array_map('str_getcsv', explode("\n", trim($content)));
    }
}
