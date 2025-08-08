<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Core/ScheduledTask/EnhancedCampaignEndTaskHandler.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\ScheduledTask;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Swag\CrowdPreOrder\Service\CampaignMailService;
use Swag\CrowdPreOrder\Service\CampaignService;

/**
 * Enhanced handler for the CampaignEndTask with payment processing
 */
class EnhancedCampaignEndTaskHandler extends ScheduledTaskHandler
{
    private EntityRepository $campaignRepository;
    private EntityRepository $pledgeRepository;
    private EntityRepository $orderRepository;
    private EntityRepository $orderTransactionRepository;
    private StateMachineRegistry $stateMachineRegistry;
    private CampaignService $campaignService;
    private CampaignMailService $mailService;

    public function __construct(
        EntityRepository $campaignRepository,
        EntityRepository $pledgeRepository,
        EntityRepository $orderRepository,
        EntityRepository $orderTransactionRepository,
        StateMachineRegistry $stateMachineRegistry,
        CampaignService $campaignService,
        CampaignMailService $mailService
    ) {
        parent::__construct();
        $this->campaignRepository = $campaignRepository;
        $this->pledgeRepository = $pledgeRepository;
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->campaignService = $campaignService;
        $this->mailService = $mailService;
    }

    public static function getHandledMessages(): iterable
    {
        return [CampaignEndTask::class];
    }

    /**
     * Execute the scheduled task
     */
    public function run(): void
    {
        $context = Context::createDefaultContext();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Find campaigns whose end date has passed and are still marked active
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new RangeFilter('endDate', ['lte' => $now]));
        $criteria->addAssociation('product');

        $campaigns = $this->campaignRepository->search($criteria, $context);

        if ($campaigns->count() === 0) {
            return;
        }

        foreach ($campaigns as $campaign) {
            $this->processCampaign($campaign, $context);
        }
    }

    /**
     * Process a single campaign
     */
    private function processCampaign($campaign, Context $context): void
    {
        $isSuccessful = $this->campaignService->isCampaignSuccessful($campaign);

        if ($isSuccessful) {
            $this->handleSuccessfulCampaign($campaign, $context);
        } else {
            $this->handleFailedCampaign($campaign, $context);
        }

        // Update campaign status
        $this->campaignRepository->update([
            [
                'id' => $campaign->getId(),
                'status' => $isSuccessful ? 'success' : 'failed',
                'active' => false,
            ]
        ], $context);
    }

    /**
     * Handle successful campaign - capture full payments
     */
    private function handleSuccessfulCampaign($campaign, Context $context): void
    {
        // Get all pledges for this campaign
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaign->getId()));
        $criteria->addAssociation('order');
        $criteria->addAssociation('customer');

        $pledges = $this->pledgeRepository->search($criteria, $context);

        foreach ($pledges as $pledge) {
            if (!$pledge->getOrderId()) {
                continue;
            }

            // Get order and its transactions
            $orderCriteria = new Criteria([$pledge->getOrderId()]);
            $orderCriteria->addAssociation('transactions');
            $order = $this->orderRepository->search($orderCriteria, $context)->first();

            if (!$order) {
                continue;
            }

            // Process each transaction
            foreach ($order->getTransactions() as $transaction) {
                // Only process if transaction is in 'authorized' state
                $currentState = $transaction->getStateMachineState()->getTechnicalName();

                if ($currentState === OrderTransactionStates::STATE_AUTHORIZED) {
                    try {
                        // Transition to paid (capture the full amount)
                        $this->stateMachineRegistry->transition(
                            new Transition(
                                'order_transaction',
                                $transaction->getId(),
                                OrderTransactionStates::STATE_PAID,
                                'stateId'
                            ),
                            $context
                        );
                    } catch (\Exception $e) {
                        // Log error - transaction might already be in target state
                        error_log('Failed to capture payment for transaction ' . $transaction->getId() . ': ' . $e->getMessage());
                    }
                }
            }

            // Update order status to in_progress
            try {
                $this->stateMachineRegistry->transition(
                    new Transition(
                        'order',
                        $order->getId(),
                        'in_progress',
                        'stateId'
                    ),
                    $context
                );
            } catch (\Exception $e) {
                // Log error
                error_log('Failed to update order status: ' . $e->getMessage());
            }
        }

        // Send success emails
        $this->mailService->sendCampaignSuccessEmails($campaign, $context);
    }

    /**
     * Handle failed campaign - refund deposits
     */
    private function handleFailedCampaign($campaign, Context $context): void
    {
        // Get all pledges for this campaign
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaign->getId()));
        $criteria->addAssociation('order');
        $criteria->addAssociation('customer');

        $pledges = $this->pledgeRepository->search($criteria, $context);

        foreach ($pledges as $pledge) {
            if (!$pledge->getOrderId()) {
                continue;
            }

            // Get order and its transactions
            $orderCriteria = new Criteria([$pledge->getOrderId()]);
            $orderCriteria->addAssociation('transactions');
            $order = $this->orderRepository->search($orderCriteria, $context)->first();

            if (!$order) {
                continue;
            }

            // Process each transaction
            foreach ($order->getTransactions() as $transaction) {
                $currentState = $transaction->getStateMachineState()->getTechnicalName();

                // Only refund if payment was captured
                if ($currentState === OrderTransactionStates::STATE_AUTHORIZED ||
                    $currentState === OrderTransactionStates::STATE_PAID) {
                    try {
                        // Transition to refunded
                        $this->stateMachineRegistry->transition(
                            new Transition(
                                'order_transaction',
                                $transaction->getId(),
                                OrderTransactionStates::STATE_REFUNDED,
                                'stateId'
                            ),
                            $context
                        );
                    } catch (\Exception $e) {
                        // Log error
                        error_log('Failed to refund transaction ' . $transaction->getId() . ': ' . $e->getMessage());
                    }
                }
            }

            // Cancel the order
            try {
                $this->stateMachineRegistry->transition(
                    new Transition(
                        'order',
                        $order->getId(),
                        'cancelled',
                        'stateId'
                    ),
                    $context
                );
            } catch (\Exception $e) {
                // Log error
                error_log('Failed to cancel order: ' . $e->getMessage());
            }
        }

        // Send failure emails
        $this->mailService->sendCampaignFailureEmails($campaign, $context);
    }
}