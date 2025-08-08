<?php
// ./custom/plugins/SwagCrowdPreOrder/src/Service/CampaignMailService.php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Service;

use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Swag\CrowdPreOrder\Core\Content\Campaign\CampaignEntity;

class CampaignMailService
{
    private MailService $mailService;
    private EntityRepository $mailTemplateRepository;
    private EntityRepository $campaignRepository;
    private EntityRepository $pledgeRepository;

    public function __construct(
        MailService $mailService,
        EntityRepository $mailTemplateRepository,
        EntityRepository $campaignRepository,
        EntityRepository $pledgeRepository
    ) {
        $this->mailService = $mailService;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->campaignRepository = $campaignRepository;
        $this->pledgeRepository = $pledgeRepository;
    }

    /**
     * Send campaign success email to all pledgers
     */
    public function sendCampaignSuccessEmails(CampaignEntity $campaign, Context $context): void
    {
        $pledges = $this->getPledgesForCampaign($campaign->getId(), $context);

        foreach ($pledges as $pledge) {
            if (!$pledge->getCustomer() || !$pledge->getCustomer()->getEmail()) {
                continue;
            }

            $data = $this->mailService->send([
                'recipients' => [
                    $pledge->getCustomer()->getEmail() => $pledge->getCustomer()->getFirstName() . ' ' . $pledge->getCustomer()->getLastName()
                ],
                'senderName' => 'Shopware Crowd PreOrder',
                'contentHtml' => $this->getCampaignSuccessHtml($campaign, $pledge),
                'contentPlain' => $this->getCampaignSuccessPlain($campaign, $pledge),
                'subject' => 'Your crowdfunding campaign "' . $campaign->getTitle() . '" succeeded!',
                'salesChannelId' => $pledge->getCustomer()->getSalesChannelId()
            ], $context);
        }
    }

    /**
     * Send campaign failure email to all pledgers
     */
    public function sendCampaignFailureEmails(CampaignEntity $campaign, Context $context): void
    {
        $pledges = $this->getPledgesForCampaign($campaign->getId(), $context);

        foreach ($pledges as $pledge) {
            if (!$pledge->getCustomer() || !$pledge->getCustomer()->getEmail()) {
                continue;
            }

            $data = $this->mailService->send([
                'recipients' => [
                    $pledge->getCustomer()->getEmail() => $pledge->getCustomer()->getFirstName() . ' ' . $pledge->getCustomer()->getLastName()
                ],
                'senderName' => 'Shopware Crowd PreOrder',
                'contentHtml' => $this->getCampaignFailureHtml($campaign, $pledge),
                'contentPlain' => $this->getCampaignFailurePlain($campaign, $pledge),
                'subject' => 'Campaign "' . $campaign->getTitle() . '" update',
                'salesChannelId' => $pledge->getCustomer()->getSalesChannelId()
            ], $context);
        }
    }

    /**
     * Get all pledges for a campaign
     */
    private function getPledgesForCampaign(string $campaignId, Context $context): \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('campaignId', $campaignId));
        $criteria->addAssociation('customer');
        $criteria->addAssociation('order');

        return $this->pledgeRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Generate HTML content for campaign success email
     */
    private function getCampaignSuccessHtml(CampaignEntity $campaign, $pledge): string
    {
        $html = '<h2>Great news! The campaign has succeeded!</h2>';
        $html .= '<p>Dear ' . $pledge->getCustomer()->getFirstName() . ',</p>';
        $html .= '<p>We\'re excited to inform you that the crowdfunding campaign "<strong>' . $campaign->getTitle() . '</strong>" has successfully reached its goal!</p>';
        $html .= '<h3>Your Pledge Details:</h3>';
        $html .= '<ul>';
        $html .= '<li>Quantity pledged: ' . $pledge->getQuantity() . '</li>';
        $html .= '<li>Amount: ' . number_format($pledge->getPledgeAmount(), 2) . ' EUR</li>';
        $html .= '</ul>';
        $html .= '<p>We will now proceed with production and charge the remaining balance. You will receive shipping information soon.</p>';
        $html .= '<p>Thank you for your support!</p>';

        return $html;
    }

    /**
     * Generate plain text content for campaign success email
     */
    private function getCampaignSuccessPlain(CampaignEntity $campaign, $pledge): string
    {
        $text = "Great news! The campaign has succeeded!\n\n";
        $text .= "Dear " . $pledge->getCustomer()->getFirstName() . ",\n\n";
        $text .= "We're excited to inform you that the crowdfunding campaign \"" . $campaign->getTitle() . "\" has successfully reached its goal!\n\n";
        $text .= "Your Pledge Details:\n";
        $text .= "- Quantity pledged: " . $pledge->getQuantity() . "\n";
        $text .= "- Amount: " . number_format($pledge->getPledgeAmount(), 2) . " EUR\n\n";
        $text .= "We will now proceed with production and charge the remaining balance. You will receive shipping information soon.\n\n";
        $text .= "Thank you for your support!\n";

        return $text;
    }

    /**
     * Generate HTML content for campaign failure email
     */
    private function getCampaignFailureHtml(CampaignEntity $campaign, $pledge): string
    {
        $html = '<h2>Campaign Update</h2>';
        $html .= '<p>Dear ' . $pledge->getCustomer()->getFirstName() . ',</p>';
        $html .= '<p>Unfortunately, the crowdfunding campaign "<strong>' . $campaign->getTitle() . '</strong>" did not reach its funding goal.</p>';
        $html .= '<p>Your pledge of ' . number_format($pledge->getPledgeAmount(), 2) . ' EUR will be refunded in full within 3-5 business days.</p>';
        $html .= '<p>We appreciate your interest and hope you\'ll participate in future campaigns!</p>';

        return $html;
    }

    /**
     * Generate plain text content for campaign failure email
     */
    private function getCampaignFailurePlain(CampaignEntity $campaign, $pledge): string
    {
        $text = "Campaign Update\n\n";
        $text .= "Dear " . $pledge->getCustomer()->getFirstName() . ",\n\n";
        $text .= "Unfortunately, the crowdfunding campaign \"" . $campaign->getTitle() . "\" did not reach its funding goal.\n\n";
        $text .= "Your pledge of " . number_format($pledge->getPledgeAmount(), 2) . " EUR will be refunded in full within 3-5 business days.\n\n";
        $text .= "We appreciate your interest and hope you'll participate in future campaigns!\n";

        return $text;
    }
}