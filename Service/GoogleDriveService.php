<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

/**
 * Uploads CSV files to Google Drive using a Service Account.
 * Pure cURL + openssl — no external Composer packages required.
 */
class GoogleDriveService
{
    private const DRIVE_UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';
    private const DRIVE_FILES_URL  = 'https://www.googleapis.com/drive/v3/files';
    private const TOKEN_URL        = 'https://oauth2.googleapis.com/token';
    private const SCOPE            = 'https://www.googleapis.com/auth/drive.file';

    private ?string $accessToken  = null;
    private int     $tokenExpires = 0;

    public function __construct(
        private readonly Config          $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @throws \RuntimeException
     */
    public function uploadFile(string $localFilePath, string $driveFilename): string
    {
        if (!file_exists($localFilePath)) {
            throw new \RuntimeException("File not found for Google Drive upload: {$localFilePath}");
        }

        $token    = $this->getAccessToken();
        $folderId = $this->config->getGoogleSheetsSpreadsheetId();

        if ($this->config->isGoogleDriveOverwriteExisting()) {
            $existingId = $this->findExistingFile($driveFilename, $folderId, $token);
            if ($existingId) {
                return $this->updateFile($existingId, $localFilePath, $token);
            }
        }

        return $this->createFile($localFilePath, $driveFilename, $folderId, $token);
    }

    // ── Private: file operations ─────────────────────────────────────────────

    private function createFile(string $localPath, string $filename, string $folderId, string $token): string
    {
        $metadata = ['name' => $filename, 'mimeType' => 'text/csv'];
        if ($folderId) {
            $metadata['parents'] = [$folderId];
        }

        $sharedDriveId = $this->config->getGoogleSheetsSheetName();
        $queryParams   = $sharedDriveId ? '?uploadType=multipart&supportsAllDrives=true' : '?uploadType=multipart';

        $response = $this->multipartRequest(
            self::DRIVE_UPLOAD_URL . $queryParams,
            $metadata,
            $localPath,
            $token,
            'POST'
        );

        $fileId = $response['id'] ?? null;
        if (!$fileId) {
            throw new \RuntimeException('Google Drive did not return a file ID after upload.');
        }

        $this->logger->info('[GoogleDrive] File created', ['file_id' => $fileId, 'name' => $filename]);
        return $fileId;
    }

    private function updateFile(string $fileId, string $localPath, string $token): string
    {
        $sharedDriveId = $this->config->getGoogleSheetsSheetName();
        $queryParams   = $sharedDriveId ? '?uploadType=multipart&supportsAllDrives=true' : '?uploadType=multipart';

        $response = $this->multipartRequest(
            self::DRIVE_UPLOAD_URL . "/{$fileId}" . $queryParams,
            ['mimeType' => 'text/csv'],
            $localPath,
            $token,
            'PATCH'
        );

        $returnedId = $response['id'] ?? $fileId;
        $this->logger->info('[GoogleDrive] File updated', ['file_id' => $returnedId]);
        return $returnedId;
    }

    private function findExistingFile(string $filename, string $folderId, string $token): ?string
    {
        $escaped = addslashes($filename);
        $query   = "name='{$escaped}' and trashed=false";
        if ($folderId) {
            $query .= " and '{$folderId}' in parents";
        }

        $sharedDriveId = $this->config->getGoogleSheetsSheetName();
        $params        = http_build_query(array_filter([
            'q'                         => $query,
            'fields'                    => 'files(id,name)',
            'includeItemsFromAllDrives' => $sharedDriveId ? 'true' : null,
            'supportsAllDrives'         => $sharedDriveId ? 'true' : null,
            'driveId'                   => $sharedDriveId ?: null,
            'corpora'                   => $sharedDriveId ? 'drive' : null,
        ]));

        $ch = curl_init(self::DRIVE_FILES_URL . '?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT        => 20,
        ]);
        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logger->warning('[GoogleDrive] File search failed', ['http_code' => $httpCode]);
            return null;
        }

        $data  = json_decode((string) $body, true);
        $files = $data['files'] ?? [];
        return !empty($files) ? $files[0]['id'] : null;
    }

    // ── Private: HTTP ────────────────────────────────────────────────────────

    private function multipartRequest(
        string $url,
        array  $metadata,
        string $localPath,
        string $token,
        string $method
    ): array {
        $boundary    = '-------boundary_' . uniqid();
        $fileContent = file_get_contents($localPath);

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode($metadata) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/csv\r\n\r\n"
            . $fileContent . "\r\n"
            . "--{$boundary}--";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                "Content-Type: multipart/related; boundary={$boundary}",
                'Content-Length: ' . strlen($body),
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('cURL error during Drive upload: ' . $curlError);
        }

        $decoded = json_decode((string) $response, true) ?? [];

        if (!in_array($httpCode, [200, 201], true)) {
            $msg = $decoded['error']['message'] ?? $response;
            throw new \RuntimeException("Google Drive API error [{$httpCode}]: {$msg}");
        }

        return $decoded;
    }

    // ── Private: auth ────────────────────────────────────────────────────────

    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpires - 30) {
            return $this->accessToken;
        }

        $credentials           = $this->loadServiceAccountCredentials();
        $jwt                   = $this->buildJwt($credentials);
        $tokenData             = $this->exchangeJwtForToken($jwt);
        $this->accessToken     = $tokenData['access_token'];
        $this->tokenExpires    = time() + ($tokenData['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function loadServiceAccountCredentials(): array
    {
        $json = $this->config->getGoogleDriveServiceAccountJson();
        if (empty($json)) {
            throw new \RuntimeException('Google Drive Service Account JSON is not configured.');
        }

        $credentials = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Google Drive Service Account JSON is invalid: ' . json_last_error_msg());
        }

        foreach (['client_email', 'private_key', 'token_uri'] as $field) {
            if (empty($credentials[$field])) {
                throw new \RuntimeException("Service Account JSON missing required field: {$field}");
            }
        }

        return $credentials;
    }

    private function buildJwt(array $credentials): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim  = $this->base64UrlEncode(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $credentials['token_uri'],
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $unsigned  = "{$header}.{$claim}";
        $signature = '';

        if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Failed to sign JWT for Google Drive auth. Check private_key in Service Account JSON.');
        }

        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    private function exchangeJwtForToken(string $jwt): array
    {
        $postData = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('cURL error during token exchange: ' . $curlError);
        }

        $data = json_decode((string) $response, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? $data['error'] ?? $response;
            throw new \RuntimeException("Google OAuth2 token exchange failed [{$httpCode}]: {$msg}");
        }

        return $data;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
