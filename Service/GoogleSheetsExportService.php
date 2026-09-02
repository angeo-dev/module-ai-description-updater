<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

/**
 * Writes rows to a Google Spreadsheet using the Sheets API v4.
 * Uses a Service Account JWT — no external Composer packages required.
 *
 * Setup:
 *   1. Create a Service Account in Google Cloud Console
 *   2. Enable Google Sheets API for the project
 *   3. Share the spreadsheet with the service account email
 *   4. Paste the JSON key in Stores → Config → Angeo → Google Sheets Export
 */
class GoogleSheetsExportService
{
    private const SHEETS_BASE  = 'https://sheets.googleapis.com/v4/spreadsheets';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const SCOPE        = 'https://www.googleapis.com/auth/spreadsheets';

    private ?string $accessToken  = null;
    private int     $tokenExpires = 0;

    public function __construct(
        private readonly Config          $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Write headers + rows to the configured spreadsheet.
     * Clears the sheet first if "Clear Sheet Before Write" is enabled.
     *
     * @param string[] $headers
     * @param array<array<string>> $rows
     */
    public function writeRows(array $headers, array $rows): void
    {
        $spreadsheetId = $this->config->getGoogleSheetsSpreadsheetId();
        $sheetName     = $this->config->getGoogleSheetsSheetName();

        if ($spreadsheetId === '') {
            throw new \RuntimeException('Google Sheets Spreadsheet ID is not configured.');
        }

        $token  = $this->getAccessToken();
        $range  = $sheetName . '!A1';
        $values = array_merge([$headers], $rows);

        if ($this->config->isGoogleDriveOverwriteExisting()) {
            $this->clearSheet($spreadsheetId, $sheetName, $token);
        }

        $this->appendValues($spreadsheetId, $range, $values, $token);

        $this->logger->info('[GoogleSheets] Written rows', [
            'spreadsheet_id' => $spreadsheetId,
            'sheet'          => $sheetName,
            'rows'           => count($rows),
        ]);
    }

    // ── Private: Sheets API ──────────────────────────────────────────────────

    private function clearSheet(string $spreadsheetId, string $sheetName, string $token): void
    {
        $url = sprintf(
            '%s/%s/values/%s:clear',
            self::SHEETS_BASE,
            urlencode($spreadsheetId),
            urlencode($sheetName)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("Google Sheets clear cURL error: {$curlErr}");
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string) $body, true) ?? [];
            $msg     = $decoded['error']['message'] ?? mb_substr((string) $body, 0, 200);
            throw new \RuntimeException("Google Sheets clear failed [{$httpCode}]: {$msg}");
        }

        $this->logger->info('[GoogleSheets] Sheet cleared', ['sheet' => $sheetName]);
    }

    private function appendValues(string $spreadsheetId, string $range, array $values, string $token): void
    {
        $url = sprintf(
            '%s/%s/values/%s:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            self::SHEETS_BASE,
            urlencode($spreadsheetId),
            urlencode($range)
        );

        $payload = json_encode(['values' => $values]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ],
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("Google Sheets append cURL error: {$curlErr}");
        }

        if ($httpCode !== 200) {
            $decoded = json_decode((string) $body, true) ?? [];
            $msg     = $decoded['error']['message'] ?? mb_substr((string) $body, 0, 200);
            throw new \RuntimeException("Google Sheets append failed [{$httpCode}]: {$msg}");
        }
    }

    // ── Private: auth ────────────────────────────────────────────────────────

    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpires - 30) {
            return $this->accessToken;
        }

        $credentials        = $this->loadCredentials();
        $jwt                = $this->buildJwt($credentials);
        $tokenData          = $this->exchangeJwt($jwt);
        $this->accessToken  = $tokenData['access_token'];
        $this->tokenExpires = time() + ($tokenData['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function loadCredentials(): array
    {
        $json = $this->config->getGoogleDriveServiceAccountJson();
        if (empty($json)) {
            throw new \RuntimeException('Service Account JSON is not configured.');
        }

        $credentials = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Service Account JSON is invalid: ' . json_last_error_msg());
        }

        foreach (['client_email', 'private_key', 'token_uri'] as $field) {
            if (empty($credentials[$field])) {
                throw new \RuntimeException("Service Account JSON missing field: {$field}");
            }
        }

        return $credentials;
    }

    private function buildJwt(array $credentials): string
    {
        $now    = time();
        $header = $this->b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim  = $this->b64u(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $credentials['token_uri'],
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $unsigned  = "{$header}.{$claim}";
        $signature = '';

        if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign JWT. Check private_key in Service Account JSON.');
        }

        return $unsigned . '.' . $this->b64u($signature);
    }

    private function exchangeJwt(string $jwt): array
    {
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("Token exchange cURL error: {$curlErr}");
        }

        $data = json_decode((string) $response, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? $data['error'] ?? $response;
            throw new \RuntimeException("OAuth2 token exchange failed [{$httpCode}]: {$msg}");
        }

        return $data;
    }

    private function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
