<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * This migration installs two separate mail templates for the crowd
 * funding campaigns: one for successful campaigns and one for failed
 * campaigns. Each template has its own template type and associated
 * translations for English and German. The entities are inserted
 * with IGNORE semantics to avoid duplicate key errors on repeated
 * installations.
 */
class Migration20250808130000MailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 20250808130000;
    }

    public function update(Connection $connection): void
    {
        // Define the two template types with their localisation strings
        $templateTypes = [
            'crowd_campaign_success' => [
                'en' => [
                    'subject' => 'Your crowdfunding campaign succeeded',
                    'content' => 'Congratulations! The campaign {{ campaign.title }} has met its goal.\n\nYou will receive shipping details shortly.',
                ],
                'de' => [
                    'subject' => 'Ihre Crowdfunding-Kampagne war erfolgreich',
                    'content' => 'Herzlichen Glückwunsch! Die Kampagne {{ campaign.title }} hat das Ziel erreicht.\n\nSie erhalten weitere Informationen zur Lieferung in Kürze.',
                ],
            ],
            'crowd_campaign_failure' => [
                'en' => [
                    'subject' => 'Your crowdfunding campaign failed',
                    'content' => 'Unfortunately the campaign {{ campaign.title }} did not reach its goal.\n\nAny deposits you made will be refunded.',
                ],
                'de' => [
                    'subject' => 'Ihre Crowdfunding-Kampagne war leider nicht erfolgreich',
                    'content' => 'Die Kampagne {{ campaign.title }} hat das Ziel leider nicht erreicht.\n\nEtwaige geleistete Anzahlungen werden zurückerstattet.',
                ],
            ],
        ];

        // Fetch language IDs and locale codes for en-GB and de-DE
        $languages = $connection->fetchAllAssociative(
            'SELECT l.id, lc.code FROM language l
                INNER JOIN locale lc ON lc.id = l.locale_id
                WHERE lc.code IN (:codes)',
            ['codes' => ['en-GB', 'de-DE']],
            ['codes' => Connection::PARAM_STR_ARRAY]
        );

        // Loop over each template type and create type, template and translations
        foreach ($templateTypes as $technicalName => $localised) {
            $typeId = Uuid::randomBytes();
            $connection->executeStatement(
                'INSERT IGNORE INTO mail_template_type (id, technical_name, available_entities, created_at) VALUES (:id, :technical_name, :entities, NOW(3))',
                [
                    'id' => $typeId,
                    'technical_name' => $technicalName,
                    'entities' => json_encode(['campaign' => 'campaign', 'customer' => 'customer'], JSON_THROW_ON_ERROR),
                ]
            );

            // Insert mail template record for this type
            $templateId = Uuid::randomBytes();
            $connection->executeStatement(
                'INSERT IGNORE INTO mail_template (id, mail_template_type_id, system_default, created_at) VALUES (:id, :typeId, 0, NOW(3))',
                [
                    'id' => $templateId,
                    'typeId' => $typeId,
                ]
            );

            // Build translation rows per language
            foreach ($languages as $lang) {
                $languageId = $lang['id'];
                $code = $lang['code'] === 'de-DE' ? 'de' : 'en';
                $subject = $localised[$code]['subject'];
                $content = $localised[$code]['content'];
                $now = (new \DateTime())->format('Y-m-d H:i:s.000');
                $connection->executeStatement(
                    'INSERT IGNORE INTO mail_template_translation
                        (mail_template_id, language_id, sender_name, subject, content_html, content_plain, created_at)
                        VALUES (:mail_template_id, :language_id, :sender_name, :subject, :content_html, :content_plain, :created_at)',
                    [
                        'mail_template_id' => $templateId,
                        'language_id' => $languageId,
                        'sender_name' => 'Shopware Crowd PreOrder',
                        'subject' => $subject,
                        'content_html' => '<p>' . nl2br($content) . '</p>',
                        'content_plain' => $content,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive updates
    }
}