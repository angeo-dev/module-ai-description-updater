<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Controller\Adminhtml\Run;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Angeo\AiDescriptionUpdater\Service\DescriptionUpdaterService;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Angeo_AiDescriptionUpdater::run';

    public function __construct(
        Context $context,
        private readonly JsonFactory                $jsonFactory,
        private readonly DescriptionUpdaterService  $updaterService,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        try {
            $summary = $this->updaterService->run();
            $result->setData([
                'success' => true,
                'message' => sprintf(
                    'Done. Processed: %d | Errors: %d | Dry run: %s',
                    $summary['processed'] ?? 0,
                    $summary['errors']    ?? 0,
                    ($summary['dry_run'] ?? false) ? 'Yes' : 'No'
                ),
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }

        return $result;
    }
}
