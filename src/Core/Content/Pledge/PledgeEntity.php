<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Core\Content\Pledge;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class PledgeEntity extends Entity
{
    use EntityIdTrait;

    protected string $campaignId;
    protected ?CampaignEntity $campaign = null;
    protected ?string $orderId;
    protected ?OrderEntity $order = null;
    protected string $customerId;
    protected ?CustomerEntity $customer = null;
    protected int $quantity;
    protected ?float $pledgeAmount;
    protected ?string $priceTier;

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
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }
    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }
    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }
    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }
    public function getCustomerId(): string
    {
        return $this->customerId;
    }
    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }
    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }
    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
    public function getQuantity(): int
    {
        return $this->quantity;
    }
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }
    public function getPledgeAmount(): ?float
    {
        return $this->pledgeAmount;
    }
    public function setPledgeAmount(?float $pledgeAmount): void
    {
        $this->pledgeAmount = $pledgeAmount;
    }
    public function getPriceTier(): ?string
    {
        return $this->priceTier;
    }
    public function setPriceTier(?string $priceTier): void
    {
        $this->priceTier = $priceTier;
    }
}