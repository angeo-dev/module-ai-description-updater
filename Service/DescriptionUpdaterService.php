<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Model\Config;
use Angeo\AiDescriptionUpdater\Service\Security\SpreadsheetValueSanitizer;

class DescriptionUpdaterService
{
    public function __construct(
        private readonly Config                        $config,
        private readonly AiProviderService             $aiProviderService,
        private readonly MagentoProductService         $productService,
        private readonly GoogleSheetsService           $googleSheetsService,
        private readonly GoogleSheetsExportService     $googleSheetsExportService,
        private readonly GoogleDriveService            $googleDriveService,
        private readonly ProductRepositoryInterface    $productRepository,
        private readonly ProductCollectionFactory      $productCollectionFactory,
        private readonly StoreManagerInterface         $storeManager,
        private readonly DirectoryList                 $directoryList,
        private readonly State                         $appState,
        private readonly TransportBuilder              $transportBuilder,
        private readonly SpreadsheetValueSanitizer     $csvSanitizer,
        private readonly LoggerInterface               $logger,
    ) {}

    /**
     * @return array{status: string, processed: int, errors: int, dry_run: bool}
     */
    public function run(?string $sku = null, ?bool $dryRun = null, ?int $storeId = null): array
    {
        $isDryRun = $dryRun ?? $this->config->isDryRun();

        if (!$this->config->isEnabled()) {
            $this->logger->info('[Updater] Module is disabled. Skipping.');
            return ['status' => 'skipped', 'reason' => 'Module disabled', 'processed' => 0, 'errors' => 0, 'dry_run' => false];
        }

        $stores     = $this->resolveStores($storeId);
        $allResults = [];
        $errors     = 0;

        foreach ($stores as $store) {
            $sid       = (int) $store->getId();
            $storeName = $store->getName();

            // Per-store config: prompts, language and enabled attributes resolve
            // against this store view's scope.
            $enabledAttributes = $this->config->getEnabledAttributes(ScopeInterface::SCOPE_STORE, $sid);
            if (empty($enabledAttributes)) {
                $this->logger->info('[Updater] No attributes enabled for store; skipping.', ['store_id' => $sid]);
                continue;
            }

            $this->logger->info('[Updater] Processing store', ['store_id' => $sid, 'name' => $storeName]);

            $skus = $sku !== null ? [$sku] : $this->resolveSkus($sid);
            if (empty($skus)) {
                continue;
            }

            // Always enforce a batch cap to keep API spend bounded, even for the
            // Google Sheets source. The offset advances run-to-run so the whole
            // catalogue is eventually covered instead of re-processing the head.
            if ($sku === null) {
                $batchSize = $this->config->getBatchSize();
                $offset    = $this->getProcessedOffset($sid, count($skus));
                $skus      = array_slice($skus, $offset, $batchSize);
                $this->logger->info('[Updater] Batch window', [
                    'store_id' => $sid,
                    'offset'   => $offset,
                    'limit'    => $batchSize,
                    'in_batch' => count($skus),
                ]);
            }

            $this->logger->info('[Updater] Starting run', [
                'store_id'  => $sid,
                'sku_count' => count($skus),
                'provider'  => $this->aiProviderService->getActiveProviderLabel(),
                'dry_run'   => $isDryRun,
            ]);

            $processedInStore = 0;

            foreach ($skus as $s) {
                try {
                    $allResults[] = $this->emulateStore(
                        $sid,
                        fn() => $this->processOneSku($s, $sid, $storeName, $isDryRun)
                    );
                    $processedInStore++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->logger->error('[Updater] Error processing SKU', [
                        'sku'      => $s,
                        'store_id' => $sid,
                        'error'    => $e->getMessage(),
                    ]);
                    $allResults[] = [
                        'sku'        => $s,
                        'store_id'   => $sid,
                        'status'     => 'error',
                        'message'    => $e->getMessage(),
                        'attributes' => [],
                    ];
                }
            }

            if ($sku === null && !$isDryRun) {
                $this->advanceProcessedOffset($sid, $processedInStore);
            }
        }

        $exportPath = null;
        if ($this->config->isCsvExportEnabled()) {
            $exportPath = $this->saveCsv($allResults);
        }

        if ($this->config->isGoogleDriveEnabled() && $exportPath !== null) {
            $this->uploadCsvToDrive($exportPath);
        }

        if ($this->config->isGoogleDriveEnabled()) {
            $this->exportToGoogleSheets($allResults);
        }

        $summary = [
            'status'    => 'done',
            'processed' => count($allResults),
            'errors'    => $errors,
            'dry_run'   => $isDryRun,
        ];

        $this->sendNotification($summary);

        $this->logger->info('[Updater] Run complete', $summary);
        return $summary;
    }

    // -- Private ---------------------------------------------------------------

    private function processOneSku(string $sku, int $storeId, string $storeName, bool $isDryRun): array
    {
        $product   = $this->productRepository->get($sku, false, $storeId);
        $generated = $this->aiProviderService->generateAllAttributes(
            $product->getName(),
            $product->getSku(),
            $storeName,
            $storeId
        );

        if ($isDryRun) {
            $this->logger->info('[Updater][DRY RUN] Would update', [
                'sku'        => $sku,
                'store_id'   => $storeId,
                'attributes' => array_keys($generated),
            ]);
            return ['sku' => $sku, 'store_id' => $storeId, 'status' => 'dry_run', 'attributes' => $generated];
        }

        $this->productService->updateAttributes($sku, $generated, $storeId);

        return ['sku' => $sku, 'store_id' => $storeId, 'status' => 'updated', 'attributes' => $generated];
    }

    /**
     * Run a callback inside the store's area emulation so scope-aware config
     * (per-store prompts, language) resolves against the correct store view.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function emulateStore(int $storeId, callable $callback): mixed
    {
        return $this->appState->emulateAreaCode(
            Area::AREA_FRONTEND,
            function () use ($storeId, $callback) {
                $current = $this->storeManager->getStore()->getId();
                $this->storeManager->setCurrentStore($storeId);
                try {
                    return $callback();
                } finally {
                    $this->storeManager->setCurrentStore($current);
                }
            }
        );
    }

    /** @return \Magento\Store\Api\Data\StoreInterface[] */
    private function resolveStores(?int $storeId): array
    {
        if ($storeId !== null) {
            return [$this->storeManager->getStore($storeId)];
        }

        return array_values(array_filter(
            $this->storeManager->getStores(),
            fn($s) => $s->isActive() && (int) $s->getId() !== 0
        ));
    }

    /**
     * Resolve the list of SKUs to process for a store.
     *
     * For the catalogue source we load a SKU-only collection (no full product
     * hydration) to avoid loading the whole catalogue into memory.
     *
     * @return string[]
     */
    private function resolveSkus(int $storeId): array
    {
        if ($this->config->isGoogleSheetsEnabled()) {
            return $this->googleSheetsService->fetchSkus();
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', 1);
        $collection->addAttributeToSelect('sku');

        $skus = [];
        foreach ($collection->getItems() as $product) {
            $skus[] = (string) $product->getSku();
        }

        return $skus;
    }

    /**
     * @return string|null Absolute path to the written CSV, or null on failure.
     */
    private function saveCsv(array $results): ?string
    {
        try {
            $dir = $this->directoryList->getPath('var') . '/angeo/ai_description_updater';
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }

            $filepath  = $dir . '/' . $this->config->getCsvFilename();
            $fp        = fopen($filepath, 'w');
            if ($fp === false) {
                throw new \RuntimeException("Unable to open CSV file for writing: {$filepath}");
            }

            $attrCodes = array_map(fn($a) => $a->attributeCode, $this->config->getEnabledAttributes());

            fputcsv($fp, $this->csvSanitizer->escapeRow(array_merge(['sku', 'store_id', 'status'], $attrCodes)));

            foreach ($results as $row) {
                $line = [$row['sku'], $row['store_id'] ?? '', $row['status']];
                foreach ($attrCodes as $code) {
                    $line[] = $row['attributes'][$code] ?? $row['message'] ?? '';
                }
                // Escape every cell to neutralise CSV / formula injection.
                fputcsv($fp, $this->csvSanitizer->escapeRow($line));
            }

            fclose($fp);
            $this->logger->info('[Updater] CSV saved', ['path' => $filepath]);
            return $filepath;
        } catch (\Throwable $e) {
            $this->logger->error('[Updater] Failed to save CSV', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function uploadCsvToDrive(string $filepath): void
    {
        try {
            $fileId = $this->googleDriveService->uploadFile($filepath, basename($filepath));
            $this->logger->info('[Updater] CSV uploaded to Google Drive', ['file_id' => $fileId]);
        } catch (\Throwable $e) {
            $this->logger->error('[Updater] Google Drive upload failed', ['error' => $e->getMessage()]);
        }
    }

    private function exportToGoogleSheets(array $results): void
    {
        try {
            $attrCodes = array_map(fn($a) => $a->attributeCode, $this->config->getEnabledAttributes());
            $headers   = array_merge(['SKU', 'Store ID', 'Status'], array_map('ucwords', $attrCodes));
            $rows      = [];

            foreach ($results as $row) {
                $line = [$row['sku'], (string) ($row['store_id'] ?? ''), $row['status']];
                foreach ($attrCodes as $code) {
                    $line[] = $row['attributes'][$code] ?? $row['message'] ?? '';
                }
                $rows[] = $line;
            }

            // Sheets export uses valueInputOption=RAW, so no formula evaluation.
            $this->googleSheetsExportService->writeRows($headers, $rows);
            $this->logger->info('[Updater] Exported to Google Sheets', ['rows' => count($rows)]);
        } catch (\Throwable $e) {
            $this->logger->error('[Updater] Google Sheets export failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendNotification(array $summary): void
    {
        $email = $this->config->getNotifyEmail();
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $body = sprintf(
                "AI Description Updater run finished.\n\nStatus: %s\nProcessed: %d\nErrors: %d\nDry run: %s\n",
                $summary['status'] ?? 'done',
                $summary['processed'] ?? 0,
                $summary['errors'] ?? 0,
                ($summary['dry_run'] ?? false) ? 'Yes' : 'No'
            );

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('angeo_ai_description_summary')
                ->setTemplateOptions([
                    'area'  => Area::AREA_ADMINHTML,
                    'store' => $this->storeManager->getDefaultStoreView()?->getId() ?? 0,
                ])
                ->setTemplateVars(['summary' => $body])
                ->setFromByScope('general')
                ->addTo($email)
                ->getTransport();

            $transport->sendMessage();
            $this->logger->info('[Updater] Summary email sent', ['email' => $email]);
        } catch (\Throwable $e) {
            $this->logger->error('[Updater] Failed to send summary email', ['error' => $e->getMessage()]);
        }
    }

    // -- Offset tracking (file-based, per store) -------------------------------

    private function offsetFile(int $storeId): string
    {
        $dir = $this->directoryList->getPath('var') . '/angeo/ai_description_updater';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir . '/offset_store_' . $storeId . '.txt';
    }

    private function getProcessedOffset(int $storeId, int $total): int
    {
        $file = $this->offsetFile($storeId);
        if (!is_file($file)) {
            return 0;
        }
        $offset = (int) trim((string) file_get_contents($file));
        // Wrap around once the whole catalogue has been covered.
        return ($total > 0 && $offset >= $total) ? 0 : max(0, $offset);
    }

    private function advanceProcessedOffset(int $storeId, int $processed): void
    {
        try {
            $file    = $this->offsetFile($storeId);
            $current = is_file($file) ? (int) trim((string) file_get_contents($file)) : 0;
            file_put_contents($file, (string) ($current + $processed));
        } catch (\Throwable $e) {
            $this->logger->warning('[Updater] Could not persist offset', ['error' => $e->getMessage()]);
        }
    }
}
