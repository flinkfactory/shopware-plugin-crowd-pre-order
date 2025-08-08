<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Tier;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignEntity;

class TierEntity extends Entity
{
    use EntityIdTrait;

    protected string $campaignId;
    protected ?CampaignEntity $campaign = null;
    protected int $thresholdQuantity;
    protected float $price;
    public function getCampaignId(): string
    {
        return $this->campaignId;
    }
    public function setCampaignId(string $campaignId): void
    {
        $this->campaignId = $campaignId;
    }
    public function getCampaign(): ?CampaignEntity
    {
        return $this->campaign;
    }
    public function setCampaign(?CampaignEntity $campaign): void
    {
        $this->campaign = $campaign;
    }
    public function getThresholdQuantity(): int
    {
        return $this->thresholdQuantity;
    }
    public function setThresholdQuantity(int $thresholdQuantity): void
    {
        $this->thresholdQuantity = $thresholdQuantity;
    }
    public function getPrice(): float
    {
        return $this->price;
    }
    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}