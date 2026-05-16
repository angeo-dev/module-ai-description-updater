<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Controller\Adminhtml\Test;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Angeo\AiDescriptionUpdater\Service\AiProviderService;
use Angeo\AiDescriptionUpdater\Model\Config;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Angeo_AiDescriptionUpdater::run';

    public function __construct(
        Context $context,
        private readonly JsonFactory                $jsonFactory,
        private readonly AiProviderService          $aiProviderService,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Config                     $config,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $sku    = (string) $this->getRequest()->getParam('sku');

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
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }
}
