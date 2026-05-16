<?php
declare(strict_types=1);
namespace Angeo\AiDescriptionUpdater\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Angeo\AiDescriptionUpdater\Service\DescriptionUpdaterService;

class RunUpdaterCommand extends Command
{
    public function __construct(
        private readonly DescriptionUpdaterService $updaterService,
        private readonly State $appState,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('angeo:ai-description:run')
            ->setDescription('[Angeo] Generate and update product descriptions via AI (OpenAI / Claude / Gemini / Groq)')
            ->addOption('sku',     null, InputOption::VALUE_OPTIONAL, 'Process a single SKU only')
            ->addOption('store',   null, InputOption::VALUE_OPTIONAL, 'Process a single store ID only (default: all active stores)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,     'Simulate run without saving (overrides config)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting AI Description Updater...</info>');

        if ($input->getOption('dry-run')) {
            $output->writeln('<comment>Dry-run mode active — no changes will be saved.</comment>');
        }

        try {
            $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($input, $output) {
                $sku     = $input->getOption('sku') ?: null;
                $dryRun  = $input->getOption('dry-run') ?: null;
                $storeId = $input->getOption('store') !== null ? (int) $input->getOption('store') : null;

                $result = $this->updaterService->run($sku, $dryRun, $storeId);
                $output->writeln('<info>Done.</info>');
                $output->writeln(sprintf(
                    '<info>Processed: %d | Errors: %d | Dry run: %s</info>',
                    $result['processed'] ?? 0,
                    $result['errors']    ?? 0,
                    ($result['dry_run']  ?? false) ? 'yes' : 'no'
                ));
            });
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
