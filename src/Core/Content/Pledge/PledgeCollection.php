<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Pledge;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class PledgeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PledgeEntity::class;
    }
}