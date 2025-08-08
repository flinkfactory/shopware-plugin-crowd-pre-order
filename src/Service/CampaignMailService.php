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
     * Look up the ID of a mail template by its template type technical name.
     * Returns null if no template can be found for that type.
     */
    private function getTemplateIdByTechnicalName(string $technicalName, Context $context): ?string
    {
        $criteria = new Criteria();
        // join the mail template type association to filter by its technical name
        $criteria->addAssociation('mailTemplateType');
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->setLimit(1);

        $template = $this->mailTemplateRepository->search($criteria, $context)->first();
        return $template ? $template->getId() : null;
    }

    /**
     * Send campaign success email to all pledgers
     */
    public function sendCampaignSuccessEmails(CampaignEntity $campaign, Context $context): void
    {
        $pledges = $this->getPledgesForCampaign($campaign->getId(), $context);

        // Determine template ID for success emails once
        $templateId = $this->getTemplateIdByTechnicalName('crowd_campaign_success', $context);

        foreach ($pledges as $pledge) {
            $customer = $pledge->getCustomer();
            if (!$customer || !$customer->getEmail()) {
                continue;
            }

            // Fallback: if template does not exist, skip sending
            if (!$templateId) {
                continue;
            }

            $this->mailService->send([
                'templateId' => $templateId,
                'recipients' => [
                    $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName()
                ],
                'senderName' => 'Shopware Crowd PreOrder',
                'salesChannelId' => $customer->getSalesChannelId(),
            ], $context, [
                'campaign' => $campaign,
                'customer' => $customer,
                'pledge' => $pledge,
            ]);
        }
    }

    /**
     * Send campaign failure email to all pledgers
     */
    public function sendCampaignFailureEmails(CampaignEntity $campaign, Context $context): void
    {
        $pledges = $this->getPledgesForCampaign($campaign->getId(), $context);

        // Determine template ID for failure emails once
        $templateId = $this->getTemplateIdByTechnicalName('crowd_campaign_failure', $context);

        foreach ($pledges as $pledge) {
            $customer = $pledge->getCustomer();
            if (!$customer || !$customer->getEmail()) {
                continue;
            }

            if (!$templateId) {
                continue;
            }

            $this->mailService->send([
                'templateId' => $templateId,
                'recipients' => [
                    $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName()
                ],
                'senderName' => 'Shopware Crowd PreOrder',
                'salesChannelId' => $customer->getSalesChannelId(),
            ], $context, [
                'campaign' => $campaign,
                'customer' => $customer,
                'pledge' => $pledge,
            ]);
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