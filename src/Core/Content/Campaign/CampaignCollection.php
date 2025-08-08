<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Campaign;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Collection of CampaignEntity objects.
 */
class CampaignCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CampaignEntity::class;
    }
}