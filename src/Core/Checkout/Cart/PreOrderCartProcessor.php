<?php

namespace Swag\CrowdPreOrder\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartDataCollection;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Cart\Price\Definition\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\TaxRuleCollection;

/**
 * Class PreOrderCartProcessor
 *
 * This processor is intended to adjust the price of cart line items
 * participating in an active crowd‑funding campaign. The idea is that
 * customers only pay a deposit or dynamic tier price when joining a
 * campaign. For simplicity this class currently contains only a
 * no‑op implementation; it can be extended to read campaign data and
 * override line item prices based on the campaign configuration.
 */
class PreOrderCartProcessor implements CartProcessorInterface
{
    private EntityRepository $campaignRepository;
    private SystemConfigService $systemConfigService;

    public function __construct(EntityRepository $campaignRepository, SystemConfigService $systemConfigService)
    {
        $this->campaignRepository = $campaignRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): CartProcessorInterface
    {
        // There is no decoration for this processor
        throw new \Exception('Decoration pattern not implemented for PreOrderCartProcessor');
    }

    /**
     * Process the cart and optionally adjust line item prices for
     * campaign products. Shopware calls this method during cart
     * calculation. Currently this implementation does not change
     * pricing; it serves as a placeholder to demonstrate where
     * campaign logic should be applied.
     *
     * @param CartDataCollection $data A collection used to store and reuse data
     *                                 during processing
     * @param Cart               $original The original cart before calculation
     * @param Cart               $calculated The cart instance that gets modified
     * @param SalesChannelContext $context Sales channel and customer context
     */
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $calculated,
        SalesChannelContext $context
    ): void {
        // Retrieve default deposit percentage from plugin configuration
        // Fallback to 10% if not configured
        $depositPercent = (float) $this->systemConfigService->get('SwagCrowdPreOrder.config.defaultDepositPercentage');
        if ($depositPercent <= 0) {
            $depositPercent = 10.0;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Iterate over all line items in the cart and adjust price for campaign products
        foreach ($calculated->getLineItems() as $lineItem) {
            // Only handle product line items
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $productId = $lineItem->getReferencedId();
            if (!$productId) {
                continue;
            }

            // Build criteria to find an active campaign for this product
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productId', $productId));
            $criteria->addFilter(new EqualsFilter('active', true));
            $criteria->addFilter(new RangeFilter('startDate', ['lte' => $now]));
            $criteria->addFilter(new RangeFilter('endDate', ['gte' => $now]));

            $campaign = $this->campaignRepository->search($criteria, $context->getContext())->first();

            if (!$campaign) {
                continue;
            }

            // Determine the unit price from the existing price definition
            $definition = $lineItem->getPriceDefinition();
            if (!$definition instanceof QuantityPriceDefinition) {
                continue;
            }

            $unitPrice = $definition->getPrice();
            $quantity = $lineItem->getQuantity() ?? 1;

            // Calculate deposit price
            $depositPrice = $unitPrice * ($depositPercent / 100.0);

            // Create new price definition for the deposit
            $taxRules = $definition->getTaxRules() ?? new TaxRuleCollection();
            $newDefinition = new QuantityPriceDefinition(
                $depositPrice,
                $taxRules,
                $context->getContext()->getCurrencyPrecision(),
                $quantity
            );
            $lineItem->setPriceDefinition($newDefinition);

            // Mark the line item with the campaign ID for later reference
            $lineItem->setPayloadValue('crowdCampaignId', $campaign->getId());
        }
    }
}