<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Api/Controller/CampaignApiController.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Api\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Swag\CrowdPreOrder\Service\CampaignService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @RouteScope(scopes={"api"})
 */
class CampaignApiController extends AbstractController
{
    private CampaignService $campaignService;
    private EntityRepository $campaignRepository;
    private EntityRepository $pledgeRepository;

    public function __construct(
        CampaignService $campaignService,
        EntityRepository $campaignRepository,
        EntityRepository $pledgeRepository
    ) {
        $this->campaignService = $campaignService;
        $this->campaignRepository = $campaignRepository;
        $this->pledgeRepository = $pledgeRepository;
    }

    /**
     * @Route("/api/campaign/statistics/{campaignId}", name="api.campaign.statistics", methods={"GET"})
     */
    public function getStatistics(string $campaignId, Context $context): JsonResponse
    {
        // Use search with a criteria to retrieve the campaign. The repository does not expose get().
        $criteria = new Criteria([$campaignId]);
        $campaign = $this->campaignRepository->search($criteria, $context)->first();

        if (!$campaign) {
            return new JsonResponse(['error' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        // Get pledge statistics
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaignId));
        $criteria->addAggregation(
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation(
                'total_revenue',
                'pledgeAmount'
            )
        );
        $criteria->addAggregation(
            new \Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation(
                'avg_pledge',
                'pledgeAmount'
            )
        );

        $result = $this->pledgeRepository->search($criteria, $context);
        $aggregations = $result->getAggregations();

        return new JsonResponse([
            'campaignId' => $campaignId,
            'totalPledges' => $result->getTotal(),
            'totalRevenue' => $aggregations->get('total_revenue')->getSum(),
            'averagePledge' => $aggregations->get('avg_pledge')->getAvg(),
            'currentQuantity' => $campaign->getCurrentQuantity(),
            'targetQuantity' => $campaign->getTargetQuantity(),
            'targetRevenue' => $campaign->getTargetRevenue(),
            'progressPercent' => $this->calculateProgress($campaign),
            'daysRemaining' => $this->calculateDaysRemaining($campaign),
        ]);
    }

    /**
     * @Route("/api/campaign/clone/{campaignId}", name="api.campaign.clone", methods={"POST"})
     */
    public function cloneCampaign(string $campaignId, Context $context): JsonResponse
    {
        // Load the original campaign via criteria search
        $criteria = new Criteria([$campaignId]);
        $originalCampaign = $this->campaignRepository->search($criteria, $context)->first();

        if (!$originalCampaign) {
            return new JsonResponse(['error' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        $newCampaignData = [
            'id' => Uuid::randomHex(),
            'productId' => $originalCampaign->getProductId(),
            'title' => $originalCampaign->getTitle() . ' (Copy)',
            'targetQuantity' => $originalCampaign->getTargetQuantity(),
            'targetRevenue' => $originalCampaign->getTargetRevenue(),
            'status' => 'draft',
            'active' => false,
            'currentQuantity' => 0,
            'currentRevenue' => 0.0,
            'startDate' => new \DateTimeImmutable(),
            'endDate' => (new \DateTimeImmutable())->modify('+30 days'),
        ];

        $this->campaignRepository->create([$newCampaignData], $context);

        return new JsonResponse([
            'success' => true,
            'newCampaignId' => $newCampaignData['id'],
            'message' => 'Campaign cloned successfully'
        ]);
    }

    /**
     * @Route("/api/campaign/export-pledges/{campaignId}", name="api.campaign.export_pledges", methods={"GET"})
     */
    public function exportPledges(string $campaignId, Context $context): Response
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaignId));
        $criteria->addAssociation('customer');
        $criteria->addAssociation('order');

        $pledges = $this->pledgeRepository->search($criteria, $context);

        // Create CSV content
        $csv = "Customer Name,Email,Quantity,Amount,Date,Order ID\n";

        foreach ($pledges as $pledge) {
            $customer = $pledge->getCustomer();
            $csv .= sprintf(
                "%s,%s,%d,%.2f,%s,%s\n",
                $customer ? $customer->getFirstName() . ' ' . $customer->getLastName() : 'N/A',
                $customer ? $customer->getEmail() : 'N/A',
                $pledge->getQuantity(),
                $pledge->getPledgeAmount() ?? 0,
                $pledge->getCreatedAt() ? $pledge->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $pledge->getOrderId() ?? 'Pending'
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="campaign_pledges_' . $campaignId . '.csv"');

        return $response;
    }

    /**
     * @Route("/api/campaign/end/{campaignId}", name="api.campaign.end", methods={"POST"})
     */
    public function endCampaign(string $campaignId, Context $context): JsonResponse
    {
        // Retrieve the campaign via search API
        $criteria = new Criteria([$campaignId]);
        $campaign = $this->campaignRepository->search($criteria, $context)->first();

        if (!$campaign) {
            return new JsonResponse(['error' => 'Campaign not found'], Response::HTTP_NOT_FOUND);
        }

        $isSuccessful = $this->campaignService->isCampaignSuccessful($campaign);

        // Update campaign status
        $this->campaignRepository->update([
            [
                'id' => $campaignId,
                'active' => false,
                'status' => $isSuccessful ? 'success' : 'failed',
                'endDate' => new \DateTimeImmutable(),
            ]
        ], $context);

        // TODO: Trigger payment processing and email notifications
        // This would normally be handled by the scheduled task handler

        return new JsonResponse([
            'success' => true,
            'campaignStatus' => $isSuccessful ? 'success' : 'failed',
            'message' => $isSuccessful
                ? 'Campaign ended successfully. Payments will be processed.'
                : 'Campaign ended without reaching target. Deposits will be refunded.'
        ]);
    }

    /**
     * @Route("/api/campaign/analytics/{campaignId}", name="api.campaign.analytics", methods={"GET"})
     */
    public function getAnalytics(string $campaignId, Request $request, Context $context): JsonResponse
    {
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');

        // Get daily pledge data
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaignId));

        if ($dateFrom) {
            $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter(
                'createdAt',
                ['gte' => $dateFrom]
            ));
        }

        if ($dateTo) {
            $criteria->addFilter(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter(
                'createdAt',
                ['lte' => $dateTo]
            ));
        }

        $pledges = $this->pledgeRepository->search($criteria, $context);

        // Group by date
        $dailyData = [];
        foreach ($pledges as $pledge) {
            $date = $pledge->getCreatedAt()->format('Y-m-d');
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'count' => 0,
                    'revenue' => 0,
                ];
            }
            $dailyData[$date]['count']++;
            $dailyData[$date]['revenue'] += $pledge->getPledgeAmount() ?? 0;
        }

        return new JsonResponse([
            'campaignId' => $campaignId,
            'analytics' => [
                'daily' => $dailyData,
                'totalPledges' => $pledges->getTotal(),
                'conversionRate' => $this->calculateConversionRate($campaignId, $context),
            ]
        ]);
    }

    /**
     * Calculate campaign progress percentage
     */
    private function calculateProgress($campaign): float
    {
        $targetQuantity = $campaign->getTargetQuantity();
        $currentQuantity = $campaign->getCurrentQuantity() ?? 0;

        if ($targetQuantity > 0) {
            return min(100, ($currentQuantity / $targetQuantity) * 100);
        }

        $targetRevenue = $campaign->getTargetRevenue();
        $currentRevenue = $campaign->getCurrentRevenue() ?? 0;

        if ($targetRevenue > 0) {
            return min(100, ($currentRevenue / $targetRevenue) * 100);
        }

        return 0;
    }

    /**
     * Calculate days remaining for campaign
     */
    private function calculateDaysRemaining($campaign): int
    {
        if (!$campaign->getEndDate()) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $diff = $campaign->getEndDate()->diff($now);

        return max(0, $diff->days);
    }

    /**
     * Calculate conversion rate (simplified)
     */
    private function calculateConversionRate(string $campaignId, Context $context): float
    {
        // This would normally track page views vs. pledges
        // For now, return a dummy value
        return 2.5;
    }
}