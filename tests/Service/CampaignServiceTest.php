<?php
// ./custom/plugins/SwagCrowdPreOrder/tests/Service/CampaignServiceTest.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Tests\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignEntity;
use Swag\CrowdPreOrder\Service\CampaignService;

class CampaignServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CampaignService $campaignService;
    private EntityRepository $campaignRepository;
    private EntityRepository $pledgeRepository;
    private EntityRepository $tierRepository;
    private SystemConfigService $systemConfigService;
    private Context $context;

    protected function setUp(): void
    {
        $this->campaignRepository = $this->createMock(EntityRepository::class);
        $this->pledgeRepository = $this->createMock(EntityRepository::class);
        $this->tierRepository = $this->createMock(EntityRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->context = Context::createDefaultContext();

        $this->campaignService = new CampaignService(
            $this->campaignRepository,
            $this->pledgeRepository,
            $this->tierRepository,
            $this->systemConfigService
        );
    }

    public function testCreateCampaign(): void
    {
        $productId = Uuid::randomHex();
        $campaignData = [
            'productId' => $productId,
            'title' => 'Test Campaign',
            'targetQuantity' => 100,
            'targetRevenue' => 10000.0,
            'active' => true,
        ];

        $this->systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('SwagCrowdPreOrder.config.defaultCampaignDuration')
            ->willReturn(30);

        $this->campaignRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($data) use ($productId) {
                $campaign = $data[0];
                return $campaign['productId'] === $productId
                    && $campaign['title'] === 'Test Campaign'
                    && $campaign['targetQuantity'] === 100
                    && $campaign['currentQuantity'] === 0
                    && $campaign['status'] === 'draft';
            }), $this->context);

        $campaignId = $this->campaignService->createCampaign($campaignData, $this->context);

        $this->assertIsString($campaignId);
        $this->assertEquals(32, strlen($campaignId)); // Hex UUID length
    }

    public function testCreateCampaignWithTiers(): void
    {
        $productId = Uuid::randomHex();
        $campaignData = [
            'productId' => $productId,
            'title' => 'Campaign with Tiers',
            'targetQuantity' => 100,
            'tiers' => [
                ['thresholdQuantity' => 10, 'price' => 90.0],
                ['thresholdQuantity' => 50, 'price' => 80.0],
                ['thresholdQuantity' => 100, 'price' => 70.0],
            ],
        ];

        $this->systemConfigService
            ->method('get')
            ->willReturn(30);

        $this->campaignRepository
            ->expects($this->once())
            ->method('create');

        $this->tierRepository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($tiers) {
                return count($tiers) === 3
                    && $tiers[0]['thresholdQuantity'] === 10
                    && $tiers[0]['price'] === 90.0;
            }), $this->context);

        $campaignId = $this->campaignService->createCampaign($campaignData, $this->context);

        $this->assertIsString($campaignId);
    }

    public function testIsCampaignSuccessful(): void
    {
        $campaign = new CampaignEntity();
        $campaign->setTargetQuantity(100);
        $campaign->setCurrentQuantity(100);
        $campaign->setTargetRevenue(null);

        $result = $this->campaignService->isCampaignSuccessful($campaign);
        $this->assertTrue($result);

        // Test with quantity not met
        $campaign->setCurrentQuantity(50);
        $result = $this->campaignService->isCampaignSuccessful($campaign);
        $this->assertFalse($result);

        // Test with revenue target
        $campaign->setTargetQuantity(null);
        $campaign->setTargetRevenue(10000.0);
        $campaign->setCurrentRevenue(10000.0);
        $result = $this->campaignService->isCampaignSuccessful($campaign);
        $this->assertTrue($result);
    }

    public function testCreatePledgeForInactiveCampaign(): void
    {
        $campaignId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $campaign = new CampaignEntity();
        $campaign->setId($campaignId);
        $campaign->setActive(false);

        $this->campaignRepository
            ->expects($this->once())
            ->method('get')
            ->with($campaignId, $this->context)
            ->willReturn($campaign);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Campaign is not active');

        $this->campaignService->createPledge($campaignId, $customerId, 1, $this->context);
    }

    public function testUpdateCampaignStatistics(): void
    {
        $campaignId = Uuid::randomHex();

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getIterator')->willReturn(new \ArrayIterator([
            $this->createPledgeMock(5, 100.0),
            $this->createPledgeMock(3, 60.0),
            $this->createPledgeMock(2, 40.0),
        ]));

        $this->pledgeRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->campaignRepository
            ->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($data) use ($campaignId) {
                return $data[0]['id'] === $campaignId
                    && $data[0]['currentQuantity'] === 10 // 5 + 3 + 2
                    && $data[0]['currentRevenue'] === 200.0; // 100 + 60 + 40
            }), $this->context);

        $this->campaignService->updateCampaignStatistics($campaignId, $this->context);
    }

    private function createPledgeMock(int $quantity, float $amount): object
    {
        return new class($quantity, $amount) {
            private int $quantity;
            private float $amount;

            public function __construct(int $quantity, float $amount)
            {
                $this->quantity = $quantity;
                $this->amount = $amount;
            }

            public function getQuantity(): int
            {
                return $this->quantity;
            }

            public function getPledgeAmount(): float
            {
                return $this->amount;
            }
        };
    }
}