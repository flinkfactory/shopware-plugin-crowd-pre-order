// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/administration/src/main.js
import './module/swag-campaign';
import './api/campaign.api.service';

import localeDE from './snippet/de-DE.json';
import localeEN from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', localeDE);
Shopware.Locale.extend('en-GB', localeEN);