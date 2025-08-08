// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/administration/src/module/swag-campaign/index.js
import './page/swag-campaign-list';
import './page/swag-campaign-detail';
import './page/swag-campaign-create';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('swag-campaign', {
    type: 'plugin',
    name: 'Crowdfunding Campaigns',
    title: 'swag-campaign.general.mainMenuItemGeneral',
    description: 'swag-campaign.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'regular-rocket',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'swag-campaign-list',
            path: 'list'
        },
        detail: {
            component: 'swag-campaign-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.campaign.list'
            }
        },
        create: {
            component: 'swag-campaign-create',
            path: 'create',
            meta: {
                parentPath: 'swag.campaign.list'
            }
        }
    },

    navigation: [{
        label: 'swag-campaign.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'swag.campaign.list',
        icon: 'regular-rocket',
        parent: 'sw-marketing',
        position: 100
    }]
});