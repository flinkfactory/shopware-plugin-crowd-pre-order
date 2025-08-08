<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Migration to create the core tables for the CrowdPreOrder plugin.  The tables
 * use binary(16) columns for primary and foreign keys, which is the standard
 * format for Shopware UUIDs.  The plugin deliberately does not drop tables
 * during destructive updates to avoid data loss.
 */
class Migration20250808120000CrowdPreOrder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 20250808120000;
    }

    public function update(Connection $connection): void
    {
        // Create campaign table
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `swag_crowd_campaign` (
                `id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `start_date` DATETIME(3) NULL,
                `end_date` DATETIME(3) NULL,
                `target_quantity` INT NULL,
                `target_revenue` DOUBLE NULL,
                `current_quantity` INT NULL,
                `current_revenue` DOUBLE NULL,
                `status` VARCHAR(32) NULL,
                `active` TINYINT(1) NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.swag_crowd_campaign.product_id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Create pledge table
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `swag_crowd_pledge` (
                `id` BINARY(16) NOT NULL,
                `campaign_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NULL,
                `customer_id` BINARY(16) NOT NULL,
                `quantity` INT NOT NULL,
                `pledge_amount` DOUBLE NULL,
                `price_tier` VARCHAR(64) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.swag_crowd_pledge.campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `swag_crowd_campaign` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.swag_crowd_pledge.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.swag_crowd_pledge.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Create tier table
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `swag_crowd_tier` (
                `id` BINARY(16) NOT NULL,
                `campaign_id` BINARY(16) NOT NULL,
                `threshold_quantity` INT NULL,
                `price` DOUBLE NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.swag_crowd_tier.campaign_id` FOREIGN KEY (`campaign_id`) REFERENCES `swag_crowd_campaign` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}