<?php

declare(strict_types=1);

namespace Angeo\AiDescriptionUpdater\Cron;

use Psr\Log\LoggerInterface;
use Angeo\AiDescriptionUpdater\Model\Config;
use Angeo\AiDescriptionUpdater\Service\DescriptionUpdaterService;

class UpdateDescriptions
{
    public function __construct(
        private readonly Config                    $config,
        private readonly DescriptionUpdaterService $updaterService,
        private readonly LoggerInterface           $logger,
    ) {}

    public function execute(): void
    {
        if (!$this->config->isCronEnabled()) {
            return;
        }
        $this->logger->info('[Cron] AI Description Updater started');
        $result = $this->updaterService->run();
        $this->logger->info('[Cron] AI Description Updater finished', $result);
    }
}
