<?php

namespace Swag\CrowdPreOrder\Core\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Scheduled task that periodically checks crowd‑funding campaigns
 * whose end date has passed and performs necessary actions such as
 * finalising pledges, creating orders or refunding pledges if the
 * goal has not been reached. The interval can be configured
 * globally in this class; here we run every hour by default.
 */
class CampaignEndTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'swag_crowd_pre_order.campaign_end_task';
    }

    public static function getDefaultInterval(): int
    {
        // Run every hour (3600 seconds)
        return 60 * 60;
    }
}