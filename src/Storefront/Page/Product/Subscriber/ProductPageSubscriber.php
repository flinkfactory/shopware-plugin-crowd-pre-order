<?php

namespace Swag\CrowdPreOrder\Storefront\Page\Product\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * This subscriber listens for product page load events in the storefront
 * and attaches the active crowd‑funding campaign to the product as
 * an extension. Storefront templates can then access the
 * campaign via `product.extensions.crowdCampaign` to render a
 * progress bar or call‑to‑action.
 */
class ProductPageSubscriber implements EventSubscriberInterface
{
    private EntityRepository $campaignRepository;

    public function __construct(EntityRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $product = $event->getPage()->getProduct();
        if (!$product) {
            return;
        }

        $productId = $product->getId();
        $context = Context::createDefaultContext();

        // Build criteria to find an active campaign for this product
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('active', true));
        // Only include campaigns where current date lies between startDate and endDate
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $criteria->addFilter(new RangeFilter('startDate', ['lte' => $now]));
        $criteria->addFilter(new RangeFilter('endDate', ['gte' => $now]));

        $campaign = $this->campaignRepository->search($criteria, $context)->first();
        if ($campaign) {
            // Attach the campaign as an extension on the product
            $product->addExtension('crowdCampaign', $campaign);
        }
    }
}