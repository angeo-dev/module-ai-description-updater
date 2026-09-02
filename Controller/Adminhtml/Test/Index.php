<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Controller\Adminhtml\Test;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Service\AiProviderService;

/**
 * Generates a preview for a single SKU. POST-only (admin form-key / CSRF).
 */
class Index extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_AiDescriptionUpdater::run';

    public function __construct(
        Context $context,
        private readonly JsonFactory                $jsonFactory,
        private readonly AiProviderService          $aiProviderService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface            $logger,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sku    = trim((string) $this->getRequest()->getParam('sku'));

        if ($sku === '') {
            return $result->setData(['success' => false, 'message' => 'SKU is required.']);
        }

        try {
            $product   = $this->productRepository->get($sku);
            $generated = $this->aiProviderService->generateAllAttributes(
                $product->getName(),
                $product->getSku()
            );
            $result->setData([
                'success'    => true,
                'sku'        => $sku,
                'provider'   => $this->aiProviderService->getActiveProviderLabel(),
                'attributes' => $generated,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('[Test] Preview generation failed', [
                'sku'       => $sku,
                'exception' => $e->getMessage(),
            ]);
            $result->setData([
                'success' => false,
                'message' => 'Could not generate a preview for this SKU. Check the log for details.',
            ]);
        }

        return $result;
    }
}
