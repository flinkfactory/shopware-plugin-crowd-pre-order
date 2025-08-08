<?php
declare(strict_types=1);

namespace Swag\CrowdPreOrder;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Entry point for the CrowdPreOrder plugin.  The plugin registers custom
 * entities, cart processors and scheduled tasks via the DI container.  Most
 * configuration is handled in service definition XML files in Resources/config.
 */
class SwagCrowdPreOrder extends Plugin
{
    /**
     * During installation we ensure that scheduled tasks are registered and
     * database migrations are executed.  Shopware handles migrations
     * automatically when the plugin is installed or updated.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        // Additional installation logic could go here, such as registering
        // default configuration values.  For now we rely on config.xml
        // definitions being loaded automatically.
    }

    /**
     * During uninstallation we can clean up any data if necessary.  In this
     * simplified plugin we do not remove any tables or mail templates to
     * preserve data consistency (per Shopware docs).  To drop tables you
     * could call $this->container->get('migration.executor')->uninstall(), but
     * leaving data intact is safer.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
    }
}