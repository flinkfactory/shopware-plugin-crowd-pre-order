<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Storefront/Controller/CampaignController.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Storefront\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Swag\CrowdPreOrder\Service\CampaignService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class CampaignController extends StorefrontController
{
    private CampaignService $campaignService;
    private CartService $cartService;

    public function __construct(CampaignService $campaignService, CartService $cartService)
    {
        $this->campaignService = $campaignService;
        $this->cartService = $cartService;
    }

    /**
     * @Route("/campaign/pledge", name="frontend.campaign.pledge", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     */
    public function pledge(Request $request, SalesChannelContext $context): JsonResponse
    {
        try {
            $productId = $request->request->get('productId');
            $quantity = (int) $request->request->get('quantity', 1);

            if (!$productId || $quantity <= 0) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid request parameters'], 400);
            }

            // Get active campaign for product
            $campaign = $this->campaignService->getActiveCampaignForProduct($productId, $context->getContext());

            if (!$campaign) {
                return new JsonResponse(['success' => false, 'message' => 'No active campaign for this product'], 404);
            }

            // Check if customer is logged in
            $customer = $context->getCustomer();
            if (!$customer) {
                return new JsonResponse(['success' => false, 'message' => 'Please login to pledge'], 401);
            }

            // Create pledge
            $pledgeId = $this->campaignService->createPledge(
                $campaign->getId(),
                $customer->getId(),
                $quantity,
                $context->getContext()
            );

            // Add to cart with special line item type
            $this->addPledgeToCart($campaign->getId(), $pledgeId, $productId, $quantity, $context);

            return new JsonResponse([
                'success' => true,
                'pledgeId' => $pledgeId,
                'message' => 'Successfully pledged to campaign'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/campaign/{campaignId}/status", name="frontend.campaign.status", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function status(string $campaignId, SalesChannelContext $context): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getCampaign($campaignId, $context->getContext());

            if (!$campaign) {
                return new JsonResponse(['success' => false, 'message' => 'Campaign not found'], 404);
            }

            $targetQuantity = $campaign->getTargetQuantity() ?? 0;
            $currentQuantity = $campaign->getCurrentQuantity() ?? 0;
            $targetRevenue = $campaign->getTargetRevenue() ?? 0;
            $currentRevenue = $campaign->getCurrentRevenue() ?? 0;

            // Calculate progress percentages
            $quantityProgress = $targetQuantity > 0 ? ($currentQuantity / $targetQuantity) * 100 : 0;
            $revenueProgress = $targetRevenue > 0 ? ($currentRevenue / $targetRevenue) * 100 : 0;

            // Calculate time remaining
            $endDate = $campaign->getEndDate();
            $now = new \DateTimeImmutable();
            $timeRemaining = $endDate ? $endDate->diff($now) : null;

            return new JsonResponse([
                'success' => true,
                'campaign' => [
                    'id' => $campaign->getId(),
                    'title' => $campaign->getTitle(),
                    'status' => $campaign->getStatus(),
                    'active' => $campaign->isActive(),
                    'currentQuantity' => $currentQuantity,
                    'targetQuantity' => $targetQuantity,
                    'quantityProgress' => round($quantityProgress, 2),
                    'currentRevenue' => $currentRevenue,
                    'targetRevenue' => $targetRevenue,
                    'revenueProgress' => round($revenueProgress, 2),
                    'daysRemaining' => $timeRemaining ? $timeRemaining->days : 0,
                    'hoursRemaining' => $timeRemaining ? $timeRemaining->h : 0,
                    'endDate' => $endDate ? $endDate->format('Y-m-d H:i:s') : null,
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add pledge to cart as special line item
     */
    private function addPledgeToCart(
        string $campaignId,
        string $pledgeId,
        string $productId,
        int $quantity,
        SalesChannelContext $context
    ): void {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // Create a regular product line item instead of a custom type. The cart processor
        // will adjust its price based on the campaign configuration. We still use the
        // pledge ID as the line item key so we can reference this pledge later.
        $lineItem = new LineItem(
            $pledgeId,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $productId,
            $quantity
        );

        $lineItem->setLabel('Pre-order Pledge');
        $lineItem->setRemovable(true);
        $lineItem->setStackable(false);

        // Add payload data to identify this as a pledge
        $lineItem->setPayloadValue('campaignId', $campaignId);
        $lineItem->setPayloadValue('pledgeId', $pledgeId);
        $lineItem->setPayloadValue('isPledge', true);

        $this->cartService->add($cart, $lineItem, $context);
    }
}