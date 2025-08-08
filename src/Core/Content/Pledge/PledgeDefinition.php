<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Pledge;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UuidField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;

class PledgeDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'swag_crowd_pledge';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return PledgeEntity::class;
    }

    public function getCollectionClass(): string
    {
        return PledgeCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new UuidField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new FkField('campaign_id', 'campaignId', CampaignDefinition::class))->addFlags(new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required()),
            (new FloatField('pledge_amount', 'pledgeAmount')),
            (new StringField('price_tier', 'priceTier', 64)),
            new ManyToOneAssociationField('campaign', 'campaign_id', CampaignDefinition::class, 'id', false),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            // created_at and updated_at fields to mirror the timestamps in the DB
            new CreatedAtField(),
            new UpdatedAtField()
        ]);
    }
}