<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Command/CreateTestCampaignCommand.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\CrowdPreOrder\Service\CampaignService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to create test campaigns for development
 */
class CreateTestCampaignCommand extends Command
{
    protected static $defaultName = 'swag:crowd:create-campaign';
    protected static $defaultDescription = 'Create a test crowdfunding campaign';

    private CampaignService $campaignService;
    private EntityRepository $productRepository;
    private EntityRepository $campaignRepository;

    public function __construct(
        CampaignService $campaignService,
        EntityRepository $productRepository,
        EntityRepository $campaignRepository
    ) {
        parent::__construct();
        $this->campaignService = $campaignService;
        $this->productRepository = $productRepository;
        $this->campaignRepository = $campaignRepository;
    }

    protected function configure(): void
    {
        $this
            ->addOption('title', 't', InputOption::VALUE_OPTIONAL, 'Campaign title', 'Test Campaign ' . date('Y-m-d'))
            ->addOption('product-id', 'p', InputOption::VALUE_OPTIONAL, 'Product ID (uses random if not specified)')
            ->addOption('target-quantity', 'q', InputOption::VALUE_OPTIONAL, 'Target quantity', 100)
            ->addOption('target-revenue', 'r', InputOption::VALUE_OPTIONAL, 'Target revenue', 10000)
            ->addOption('duration', 'd', InputOption::VALUE_OPTIONAL, 'Campaign duration in days', 30)
            ->addOption('with-tiers', null, InputOption::VALUE_NONE, 'Create campaign with price tiers')
            ->addOption('with-pledges', null, InputOption::VALUE_NONE, 'Create test pledges')
            ->addOption('active', 'a', InputOption::VALUE_NONE, 'Make campaign immediately active');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $io->title('Creating Test Campaign');

        // Get or find a product
        $productId = $input->getOption('product-id');
        if (!$productId) {
            $productId = $this->findRandomProduct($context);
            if (!$productId) {
                $io->error('No products found. Please create a product first.');
                return Command::FAILURE;
            }
            $io->note('Using random product: ' . $productId);
        }

        // Prepare campaign data
        $campaignData = [
            'productId' => $productId,
            'title' => $input->getOption('title'),
            'targetQuantity' => (int) $input->getOption('target-quantity'),
            'targetRevenue' => (float) $input->getOption('target-revenue'),
            'active' => $input->getOption('active'),
            'startDate' => new \DateTimeImmutable(),
            'endDate' => (new \DateTimeImmutable())->modify('+' . $input->getOption('duration') . ' days'),
        ];

        // Add tiers if requested
        if ($input->getOption('with-tiers')) {
            $campaignData['tiers'] = [
                ['thresholdQuantity' => 10, 'price' => 95.0],
                ['thresholdQuantity' => 25, 'price' => 90.0],
                ['thresholdQuantity' => 50, 'price' => 85.0],
                ['thresholdQuantity' => 100, 'price' => 80.0],
            ];
            $io->note('Adding 4 price tiers');
        }

        // Create campaign
        try {
            $campaignId = $this->campaignService->createCampaign($campaignData, $context);
            $io->success('Campaign created successfully!');
            $io->table(
                ['Field', 'Value'],
                [
                    ['Campaign ID', $campaignId],
                    ['Title', $campaignData['title']],
                    ['Product ID', $productId],
                    ['Target Quantity', $campaignData['targetQuantity']],
                    ['Target Revenue', $campaignData['targetRevenue']],
                    ['Duration', $input->getOption('duration') . ' days'],
                    ['Active', $campaignData['active'] ? 'Yes' : 'No'],
                ]
            );

            // Create test pledges if requested
            if ($input->getOption('with-pledges')) {
                $this->createTestPledges($campaignId, $context, $io);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create campaign: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Find a random product ID from the database
     */
    private function findRandomProduct(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('active', true));

        $product = $this->productRepository->search($criteria, $context)->first();
        return $product ? $product->getId() : null;
    }

    /**
     * Create test pledges for the campaign
     */
    private function createTestPledges(string $campaignId, Context $context, SymfonyStyle $io): void
    {
        $io->section('Creating Test Pledges');

        // This would normally create actual customer pledges
        // For testing, we'll just update the campaign statistics
        $pledgeCount = random_int(5, 20);
        $totalQuantity = 0;
        $totalRevenue = 0.0;

        for ($i = 0; $i < $pledgeCount; $i++) {
            $quantity = random_int(1, 5);
            $amount = $quantity * random_int(80, 100);

            $totalQuantity += $quantity;
            $totalRevenue += $amount;
        }

        // Update campaign with test data
        $this->campaignRepository->update([
            [
                'id' => $campaignId,
                'currentQuantity' => $totalQuantity,
                'currentRevenue' => $totalRevenue,
            ]
        ], $context);

        $io->success(sprintf(
            'Created %d test pledges with total quantity %d and revenue €%.2f',
            $pledgeCount,
            $totalQuantity,
            $totalRevenue
        ));
    }
}