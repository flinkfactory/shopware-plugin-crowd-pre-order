<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Tier;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class TierCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TierEntity::class;
    }
}