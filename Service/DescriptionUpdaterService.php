<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Model\Config;

class DescriptionUpdaterService
{
    public function __construct(
        private readonly Config                        $config,
        private readonly AiProviderService             $aiProviderService,
        private readonly MagentoProductService         $productService,
        private readonly GoogleSheetsService           $googleSheetsService,
        private readonly GoogleSheetsExportService     $googleSheetsExportService,
        private readonly ProductRepositoryInterface    $productRepository,
        private readonly SearchCriteriaBuilder         $searchCriteriaBuilder,
        private readonly StoreManagerInterface         $storeManager,
        private readonly DirectoryList                 $directoryList,
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

        $enabledAttributes = $this->config->getEnabledAttributes();
        if (empty($enabledAttributes)) {
            $this->logger->info('[Updater] No attributes enabled. Skipping.');
            return ['status' => 'skipped', 'reason' => 'No attributes enabled', 'processed' => 0, 'errors' => 0, 'dry_run' => false];
        }

        $stores     = $this->resolveStores($storeId);
        $allResults = [];
        $errors     = 0;

        foreach ($stores as $store) {
            $sid       = (int) $store->getId();
            $storeName = $store->getName();

            $this->logger->info('[Updater] Processing store', ['store_id' => $sid, 'name' => $storeName]);

            $skus = $sku !== null ? [$sku] : $this->resolveSkus();
            if (empty($skus)) {
                continue;
            }
            if ($sku === null && !$this->config->isGoogleSheetsEnabled()) {
                $skus = array_slice($skus, 0, $this->config->getBatchSize());
            } elseif ($sku === null && $this->config->isGoogleSheetsEnabled()) {
                $this->logger->info('[Updater] Google Sheets source — processing all SKUs without batch limit', [
                    'count' => count($skus),
                ]);
            }

            $this->logger->info('[Updater] Starting run', [
                'store_id'  => $sid,
                'sku_count' => count($skus),
                'provider'  => $this->aiProviderService->getActiveProviderLabel(),
                'dry_run'   => $isDryRun,
            ]);

            foreach ($skus as $s) {
                try {
                    $allResults[] = $this->processOneSku($s, $sid, $storeName, $isDryRun);
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
        }

        if ($this->config->isCsvExportEnabled()) {
            $this->saveCsv($allResults);
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

        $this->logger->info('[Updater] Run complete', $summary);
        return $summary;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function processOneSku(string $sku, int $storeId, string $storeName, bool $isDryRun): array
    {
        $product   = $this->productRepository->get($sku, false, $storeId);
        $generated = $this->aiProviderService->generateAllAttributes(
            $product->getName(),
            $product->getSku(),
            $storeName
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

    /** @return string[] */
    private function resolveSkus(): array
    {
        if ($this->config->isGoogleSheetsEnabled()) {
            return $this->googleSheetsService->fetchSkus();
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('status', 1)
            ->create();

        $products = $this->productRepository->getList($criteria)->getItems();
        return array_map(fn($p) => $p->getSku(), $products);
    }

    private function saveCsv(array $results): void
    {
        try {
            $dir = $this->directoryList->getPath('var') . '/angeo/ai_description_updater';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filepath  = $dir . '/' . $this->config->getCsvFilename();
            $fp        = fopen($filepath, 'w');
            $attrCodes = array_map(fn($a) => $a->attributeCode, $this->config->getEnabledAttributes());

            fputcsv($fp, array_merge(['sku', 'store_id', 'status'], $attrCodes));

            foreach ($results as $row) {
                $line = [$row['sku'], $row['store_id'] ?? '', $row['status']];
                foreach ($attrCodes as $code) {
                    $line[] = $row['attributes'][$code] ?? $row['message'] ?? '';
                }
                fputcsv($fp, $line);
            }

            fclose($fp);
            $this->logger->info('[Updater] CSV saved', ['path' => $filepath]);
        } catch (\Throwable $e) {
            $this->logger->error('[Updater] Failed to save CSV', ['error' => $e->getMessage()]);
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

            $this->googleSheetsExportService->writeRows($headers, $rows);
            $this->logger->info('[Updater] Exported to Google Sheets', ['rows' => count($rows)]);
        } catch (\Throwable $e) {
            $this->logger->error('[Updater] Google Sheets export failed', ['error' => $e->getMessage()]);
        }
    }
}
