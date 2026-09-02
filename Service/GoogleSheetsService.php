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
        $this->logger->info('[GoogleSheets] Fetching spreadsheet', [
            'spreadsheet_id' => $this->config->getSpreadsheetId(),
            'gid'            => $this->config->getSheetGid(),
        ]);

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
        // Only allow the expected Google host. Defends against SSRF if the
        // configured ID ever produced an unexpected URL.
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host !== 'docs.google.com') {
            throw new \RuntimeException('Refusing to fetch non-Google Sheets URL.');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL              => $url,
            CURLOPT_RETURNTRANSFER   => true,
            // Restrict to HTTPS and follow at most a few redirects, never to
            // non-HTTPS targets (blocks redirect-based SSRF to internal hosts).
            CURLOPT_PROTOCOLS        => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS  => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION   => true,
            CURLOPT_MAXREDIRS        => 5,
            CURLOPT_SSL_VERIFYPEER   => true,
            CURLOPT_SSL_VERIFYHOST   => 2,
            CURLOPT_TIMEOUT          => 30,
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
        return array_map(
            static fn(string $line): array => str_getcsv($line, ',', '"', '\\'),
            explode("\n", trim($content))
        );
    }
}
