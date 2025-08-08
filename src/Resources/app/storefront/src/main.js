// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/storefront/src/main.js
import CampaignWidgetPlugin from './plugin/campaign-widget.plugin';
import './scss/base.scss';

// Register the plugin
const PluginManager = window.PluginManager;
PluginManager.register('CampaignWidget', CampaignWidgetPlugin, '[data-campaign-widget]');

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        PluginManager.initializePlugins();
    });
} else {
    PluginManager.initializePlugins();
}