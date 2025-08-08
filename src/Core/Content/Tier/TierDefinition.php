<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Tier;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UuidField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

class TierDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'swag_crowd_tier';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }
    public function getEntityClass(): string
    {
        return TierEntity::class;
    }
    public function getCollectionClass(): string
    {
        return TierCollection::class;
    }
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new UuidField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('campaign_id', 'campaignId', CampaignDefinition::class))->addFlags(new Required()),
            (new IntField('threshold_quantity', 'thresholdQuantity')),
            (new FloatField('price', 'price')),
            new ManyToOneAssociationField('campaign', 'campaign_id', CampaignDefinition::class, 'id', false),
            // Timestamp fields for created_at and updated_at
            new CreatedAtField(),
            new UpdatedAtField()
        ]);
    }
}