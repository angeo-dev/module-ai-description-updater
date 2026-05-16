<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Saves generated attribute values directly via Magento ProductRepository.
 *
 * The original module made an HTTP call to Magento's own REST API with a bearer
 * token (self-request anti-pattern). This implementation uses the repository
 * directly — more efficient, no token needed, and no cURL round-trip cost.
 */
class MagentoProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface            $logger,
    ) {}

    /**
     * Update one or more attributes on a product in a specific store scope.
     *
     * @param array<string, string> $attributes ['attribute_code' => 'value', ...]
     * @throws \RuntimeException
     */
    public function updateAttributes(string $sku, array $attributes, int $storeId = 0): void
    {
        if (empty($attributes)) {
            return;
        }

        try {
            $product = $this->productRepository->get($sku, editMode: true, storeId: $storeId);
        } catch (NoSuchEntityException) {
            throw new \RuntimeException("Product not found for SKU: {$sku}");
        }

        foreach ($attributes as $code => $value) {
            $product->setCustomAttribute($code, $value);
        }

        $this->productRepository->save($product);

        $this->logger->info('[ProductService] Attributes updated', [
            'sku'        => $sku,
            'store_id'   => $storeId,
            'attributes' => array_keys($attributes),
        ]);
    }
}
