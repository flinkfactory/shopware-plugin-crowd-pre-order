<?php

namespace Swag\CrowdPreOrder\Core\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * Handler for the CampaignEndTask. This class is executed by
 * Shopware's message queue whenever the CampaignEndTask is
 * dispatched. It scans for campaigns whose end date has passed and
 * finalises or cancels them accordingly. In a real implementation
 * this would trigger order creation for successful campaigns or
 * refunds for unsuccessful ones.
 */
class CampaignEndTaskHandler extends ScheduledTaskHandler
{
    private EntityRepository $campaignRepository;
    private EntityRepository $pledgeRepository;

    public function __construct(EntityRepository $campaignRepository, EntityRepository $pledgeRepository)
    {
        // Call parent constructor to ensure internal state is initialised
        parent::__construct();
        $this->campaignRepository = $campaignRepository;
        $this->pledgeRepository = $pledgeRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [CampaignEndTask::class];
    }

    /**
     * Execute the scheduled task. This method is called by the
     * framework whenever the CampaignEndTask message is consumed.
     */
    public function run(): void
    {
        $context = Context::createDefaultContext();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Find campaigns whose end date has passed and are still marked active
        $criteria = new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria();
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter('active', true));
        $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter('endDate', ['lte' => $now]));

        $campaigns = $this->campaignRepository->search($criteria, $context);
        if ($campaigns->count() === 0) {
            return;
        }

        $updates = [];
        foreach ($campaigns as $campaign) {
            /** @var \Swag\CrowdPreOrder\Core\Content\Campaign\CampaignEntity $campaign */
            $targetQuantity = $campaign->getTargetQuantity() ?? 0;
            $currentQuantity = $campaign->getCurrentQuantity() ?? 0;
            $targetRevenue = $campaign->getTargetRevenue() ?? 0.0;
            $currentRevenue = $campaign->getCurrentRevenue() ?? 0.0;

            $success = false;
            if ($targetQuantity > 0 && $currentQuantity >= $targetQuantity) {
                $success = true;
            }
            if ($targetRevenue > 0 && $currentRevenue >= $targetRevenue) {
                $success = true;
            }

            $status = $success ? 'success' : 'failed';

            $updates[] = [
                'id'     => $campaign->getId(),
                'status' => $status,
                'active' => false,
            ];

            // TODO: In a full implementation, we would now convert
            // pledges into orders (for success) or cancel/refund (for failure).
        }

        if (!empty($updates)) {
            $this->campaignRepository->update($updates, $context);
        }
    }
}