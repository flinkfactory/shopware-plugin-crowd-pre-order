<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Core/Checkout/Order/Subscriber/OrderSubscriber.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Checkout\Order\Subscriber;

use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to handle order events and link orders to pledges
 */
class OrderSubscriber implements EventSubscriberInterface
{
    private EntityRepository $pledgeRepository;
    private EntityRepository $orderLineItemRepository;

    public function __construct(
        EntityRepository $pledgeRepository,
        EntityRepository $orderLineItemRepository
    ) {
        $this->pledgeRepository = $pledgeRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_TRANSACTION_STATE_WRITTEN_EVENT => 'onOrderTransactionStateChange',
            'checkout.order.placed' => 'onOrderPlaced',
        ];
    }

    /**
     * Handle order placement - link order to pledges
     */
    public function onOrderPlaced(\Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();

        // Check line items for pledges
        foreach ($order->getLineItems() as $lineItem) {
            $payload = $lineItem->getPayload();

            if (isset($payload['pledgeId']) && isset($payload['campaignId'])) {
                // Update pledge with order ID
                $this->pledgeRepository->update([
                    [
                        'id' => $payload['pledgeId'],
                        'orderId' => $order->getId(),
                    ]
                ], $context);
            }
        }
    }

    /**
     * Handle transaction state changes
     */
    public function onOrderTransactionStateChange(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();
        $newState = $event->getToPlace()->getTechnicalName();

        // Only process if transaction is now authorized (deposit paid)
        if ($newState !== 'authorized' && $newState !== 'paid') {
            return;
        }

        // Find pledges associated with this order
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
        $criteria->addAssociation('campaign');

        $pledges = $this->pledgeRepository->search($criteria, $context);

        foreach ($pledges as $pledge) {
            $campaign = $pledge->getCampaign();

            if (!$campaign) {
                continue;
            }

            // If transaction is paid and campaign is successful, this means full payment was captured
            if ($newState === 'paid' && $campaign->getStatus() === 'success') {
                // Order is now fully paid - could trigger fulfillment
                // This would integrate with your fulfillment system
                $this->triggerFulfillment($order, $pledge, $context);
            }
        }
    }

    /**
     * Trigger order fulfillment (placeholder for actual implementation)
     */
    private function triggerFulfillment($order, $pledge, $context): void
    {
        // Here you would:
        // 1. Update order status to "in_progress" or "processing"
        // 2. Send notification to warehouse/fulfillment system
        // 3. Update inventory
        // 4. Send confirmation email to customer

        // For now, just log or dispatch an event
        // You could dispatch a custom event here that other systems listen to
    }
}