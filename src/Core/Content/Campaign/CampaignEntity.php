<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Campaign;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Content\Product\ProductEntity;

/**
 * Represents a crowdfunding campaign.  It is a simple container for the
 * database fields defined in CampaignDefinition.  The properties must be
 * protected to allow the DAL to hydrate them correctly.
 */
class CampaignEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;
    protected ?ProductEntity $product = null;
    protected string $title;
    protected ?\DateTimeInterface $startDate;
    protected ?\DateTimeInterface $endDate;
    protected ?int $targetQuantity;
    protected ?float $targetRevenue;
    protected ?int $currentQuantity;
    protected ?float $currentRevenue;
    protected ?string $status;
    protected ?bool $active;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getTargetQuantity(): ?int
    {
        return $this->targetQuantity;
    }

    public function setTargetQuantity(?int $targetQuantity): void
    {
        $this->targetQuantity = $targetQuantity;
    }

    public function getTargetRevenue(): ?float
    {
        return $this->targetRevenue;
    }

    public function setTargetRevenue(?float $targetRevenue): void
    {
        $this->targetRevenue = $targetRevenue;
    }

    public function getCurrentQuantity(): ?int
    {
        return $this->currentQuantity;
    }

    public function setCurrentQuantity(?int $currentQuantity): void
    {
        $this->currentQuantity = $currentQuantity;
    }

    public function getCurrentRevenue(): ?float
    {
        return $this->currentRevenue;
    }

    public function setCurrentRevenue(?float $currentRevenue): void
    {
        $this->currentRevenue = $currentRevenue;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }
}