<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Service/CampaignService.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignEntity;
use Swag\CrowdPreOrder\Core\Content\Pledge\PledgeEntity;

class CampaignService
{
    private EntityRepository $campaignRepository;
    private EntityRepository $pledgeRepository;
    private EntityRepository $tierRepository;
    private SystemConfigService $systemConfigService;

    public function __construct(
        EntityRepository $campaignRepository,
        EntityRepository $pledgeRepository,
        EntityRepository $tierRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->campaignRepository = $campaignRepository;
        $this->pledgeRepository = $pledgeRepository;
        $this->tierRepository = $tierRepository;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Create a new campaign for a product
     */
    public function createCampaign(array $data, Context $context): string
    {
        $campaignId = Uuid::randomHex();

        // Set default values if not provided
        $defaultDuration = $this->systemConfigService->get('SwagCrowdPreOrder.config.defaultCampaignDuration') ?? 30;

        if (!isset($data['startDate'])) {
            $data['startDate'] = new \DateTimeImmutable();
        }

        if (!isset($data['endDate'])) {
            $data['endDate'] = $data['startDate']->modify("+{$defaultDuration} days");
        }

        $campaignData = [
            'id' => $campaignId,
            'productId' => $data['productId'],
            'title' => $data['title'],
            'startDate' => $data['startDate'],
            'endDate' => $data['endDate'],
            'targetQuantity' => $data['targetQuantity'] ?? null,
            'targetRevenue' => $data['targetRevenue'] ?? null,
            'currentQuantity' => 0,
            'currentRevenue' => 0.0,
            'status' => 'draft',
            'active' => $data['active'] ?? false,
        ];

        $this->campaignRepository->create([$campaignData], $context);

        // Create price tiers if provided
        if (isset($data['tiers']) && is_array($data['tiers'])) {
            $this->createTiers($campaignId, $data['tiers'], $context);
        }

        return $campaignId;
    }

    /**
     * Create price tiers for a campaign
     */
    public function createTiers(string $campaignId, array $tiers, Context $context): void
    {
        $tierData = [];
        foreach ($tiers as $tier) {
            $tierData[] = [
                'id' => Uuid::randomHex(),
                'campaignId' => $campaignId,
                'thresholdQuantity' => $tier['thresholdQuantity'],
                'price' => $tier['price'],
            ];
        }

        if (!empty($tierData)) {
            $this->tierRepository->create($tierData, $context);
        }
    }

    /**
     * Create a pledge for a campaign
     */
    public function createPledge(string $campaignId, string $customerId, int $quantity, Context $context): string
    {
        $campaign = $this->getCampaign($campaignId, $context);
        if (!$campaign || !$campaign->isActive()) {
            throw new \Exception('Campaign is not active');
        }

        // Check if campaign is still valid
        $now = new \DateTimeImmutable();
        if ($campaign->getEndDate() && $campaign->getEndDate() < $now) {
            throw new \Exception('Campaign has ended');
        }

        // Calculate pledge amount based on tiers
        $pledgeAmount = $this->calculatePledgeAmount($campaign, $quantity, $context);

        $pledgeId = Uuid::randomHex();
        $pledgeData = [
            'id' => $pledgeId,
            'campaignId' => $campaignId,
            'customerId' => $customerId,
            'quantity' => $quantity,
            'pledgeAmount' => $pledgeAmount,
            'priceTier' => $this->getCurrentTier($campaign, $context),
        ];

        $this->pledgeRepository->create([$pledgeData], $context);

        // Update campaign statistics
        $this->updateCampaignStatistics($campaignId, $context);

        return $pledgeId;
    }

    /**
     * Calculate pledge amount based on current tier
     */
    private function calculatePledgeAmount(CampaignEntity $campaign, int $quantity, Context $context): float
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaign->getId()));
        $criteria->addSorting(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting('thresholdQuantity', 'DESC'));

        $tiers = $this->tierRepository->search($criteria, $context);

        $currentQuantity = $campaign->getCurrentQuantity() ?? 0;
        $totalQuantity = $currentQuantity + $quantity;

        // Find applicable tier
        foreach ($tiers as $tier) {
            if ($totalQuantity >= $tier->getThresholdQuantity()) {
                return $tier->getPrice() * $quantity;
            }
        }

        // If no tier applies, use deposit percentage
        $depositPercent = $this->systemConfigService->get('SwagCrowdPreOrder.config.defaultDepositPercentage') ?? 10;
        // This would need the product price - simplified for now
        return 100.0 * $quantity * ($depositPercent / 100);
    }

    /**
     * Get current pricing tier name
     */
    private function getCurrentTier(CampaignEntity $campaign, Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaign->getId()));
        $criteria->addSorting(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting('thresholdQuantity', 'DESC'));

        $tiers = $this->tierRepository->search($criteria, $context);

        $currentQuantity = $campaign->getCurrentQuantity() ?? 0;

        foreach ($tiers as $index => $tier) {
            if ($currentQuantity >= $tier->getThresholdQuantity()) {
                return 'tier_' . ($index + 1);
            }
        }

        return 'base';
    }

    /**
     * Update campaign statistics after a pledge
     */
    public function updateCampaignStatistics(string $campaignId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaignId));

        $pledges = $this->pledgeRepository->search($criteria, $context);

        $totalQuantity = 0;
        $totalRevenue = 0.0;

        foreach ($pledges as $pledge) {
            $totalQuantity += $pledge->getQuantity();
            $totalRevenue += $pledge->getPledgeAmount() ?? 0;
        }

        $this->campaignRepository->update([
            [
                'id' => $campaignId,
                'currentQuantity' => $totalQuantity,
                'currentRevenue' => $totalRevenue,
            ]
        ], $context);
    }

    /**
     * Get active campaign for a product
     */
    public function getActiveCampaignForProduct(string $productId, Context $context): ?CampaignEntity
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new RangeFilter('startDate', ['lte' => $now]));
        $criteria->addFilter(new RangeFilter('endDate', ['gte' => $now]));
        $criteria->addAssociation('product');
        $criteria->setLimit(1);

        return $this->campaignRepository->search($criteria, $context)->first();
    }

    /**
     * Get a campaign by ID
     */
    public function getCampaign(string $campaignId, Context $context): ?CampaignEntity
    {
        // Use a criteria search rather than the non-existent get() method
        $criteria = new Criteria([$campaignId]);
        return $this->campaignRepository->search($criteria, $context)->first();
    }

    /**
     * Check if campaign goals are met
     */
    public function isCampaignSuccessful(CampaignEntity $campaign): bool
    {
        $targetQuantity = $campaign->getTargetQuantity();
        $targetRevenue = $campaign->getTargetRevenue();
        $currentQuantity = $campaign->getCurrentQuantity() ?? 0;
        $currentRevenue = $campaign->getCurrentRevenue() ?? 0.0;

        if ($targetQuantity && $currentQuantity >= $targetQuantity) {
            return true;
        }

        if ($targetRevenue && $currentRevenue >= $targetRevenue) {
            return true;
        }

        return false;
    }
}