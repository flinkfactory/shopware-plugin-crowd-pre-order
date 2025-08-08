<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Campaign;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UuidField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Content\Product\ProductDefinition;

class CampaignDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'swag_crowd_campaign';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CampaignEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CampaignCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new UuidField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            // link to product
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new StringField('title', 'title', 255))->addFlags(new Required()),
            // Use DateTimeField instead of DateField so the time component
            // from the database (DATETIME(3)) is preserved.
            (new DateTimeField('start_date', 'startDate')),
            (new DateTimeField('end_date', 'endDate')),
            (new IntField('target_quantity', 'targetQuantity')),
            (new FloatField('target_revenue', 'targetRevenue')),
            (new IntField('current_quantity', 'currentQuantity')),
            (new FloatField('current_revenue', 'currentRevenue')),
            (new StringField('status', 'status', 32)),
            (new BoolField('active', 'active')),
            // Automatic timestamp fields; these map to created_at and updated_at columns
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false)
        ]);
    }
}