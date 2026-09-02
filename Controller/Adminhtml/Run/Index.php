<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Controller\Adminhtml\Run;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Service\DescriptionUpdaterService;

/**
 * Triggers a manual run. POST-only so Magento enforces the admin form key
 * (CSRF protection); a state-changing GET endpoint would be vulnerable.
 */
class Index extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Angeo_AiDescriptionUpdater::run';

    public function __construct(
        Context $context,
        private readonly JsonFactory                $jsonFactory,
        private readonly DescriptionUpdaterService  $updaterService,
        private readonly LoggerInterface            $logger,
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
            // Log full detail; return a generic message (avoid leaking internals).
            $this->logger->error('[Run] Manual run failed', ['exception' => $e->getMessage()]);
            $result->setData([
                'success' => false,
                'message' => 'The run could not be completed. Check the log for details.',
            ]);
        }

        return $result;
    }
}
