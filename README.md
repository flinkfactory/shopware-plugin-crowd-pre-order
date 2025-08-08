# SwagCrowdPreOrder - Crowdfunded Pre-Order & Group-Buy Plugin for Shopware 6

## Overview

SwagCrowdPreOrder is a comprehensive Shopware 6 plugin that enables merchants to launch crowdfunding campaigns for products. Customers can pledge to buy products, and orders are only fulfilled if campaign targets are met.

## Features

- **Crowdfunding Campaigns**: Create time-limited campaigns with target quantities or revenue goals
- **Tiered Pricing**: Set up price tiers that automatically apply as more people join
- **Deposit System**: Customers only pay a deposit upfront; full payment is captured upon campaign success
- **Automatic Processing**: Scheduled tasks automatically close campaigns and process payments
- **Email Notifications**: Automated emails for campaign success/failure
- **Admin Interface**: Full administration module for managing campaigns
- **Storefront Widget**: Interactive campaign widget with progress bars and countdown timers

## Installation

### 1. Copy Plugin Files

Copy the plugin folder to your Shopware installation:
```bash
cp -r SwagCrowdPreOrder /path/to/shopware/custom/plugins/
```

### 2. Install Plugin

```bash
# Navigate to Shopware root directory
cd /path/to/shopware

# Refresh plugin list
bin/console plugin:refresh

# Install the plugin
bin/console plugin:install SwagCrowdPreOrder

# Activate the plugin
bin/console plugin:activate SwagCrowdPreOrder

# Clear cache
bin/console cache:clear
```

### 3. Build Assets

```bash
# Build administration assets
./psh.phar administration:build

# Build storefront assets
./psh.phar storefront:build

# Or use npm
cd custom/plugins/SwagCrowdPreOrder
npm run build
```

### 4. Run Migrations

```bash
bin/console database:migrate --all SwagCrowdPreOrder
```

## Configuration

1. Navigate to **Settings > Plugins** in the Shopware administration
2. Find **SwagCrowdPreOrder** and click **Configure**
3. Set up the following options:
    - **Default Deposit Percentage**: Percentage of product price charged as deposit (default: 10%)
    - **Default Campaign Duration**: Default number of days for campaigns (default: 30)
    - **Maximum Campaigns per Product**: Limit concurrent campaigns per product
    - **Enable Referral Program**: Toggle referral rewards

## Usage

### Creating a Campaign (Admin)

1. Navigate to **Marketing > Crowdfunding Campaigns** in the administration
2. Click **Add Campaign**
3. Fill in the campaign details:
    - Select a product
    - Set campaign title and dates
    - Define target quantity and/or revenue
    - (Optional) Add price tiers for group-buy discounts
4. Save and activate the campaign

### Managing Campaigns

- **View Campaigns**: See all campaigns with their current status and progress
- **Edit Campaigns**: Modify campaign settings (only before campaign starts)
- **Monitor Progress**: Track pledges, current quantity, and revenue in real-time
- **View Pledges**: See all customer pledges for each campaign

### Customer Experience

1. Customers visit a product page with an active campaign
2. They see the campaign widget showing:
    - Progress towards goal
    - Time remaining
    - Current price tier
    - Number of backers
3. Customers can pledge by:
    - Selecting quantity
    - Clicking "Pledge Now"
    - Completing checkout (only deposit is charged)
4. After campaign ends:
    - **Success**: Full payment is captured, order proceeds to fulfillment
    - **Failure**: Deposit is refunded, order is cancelled

## File Structure

```
SwagCrowdPreOrder/
├── composer.json
├── src/
│   ├── SwagCrowdPreOrder.php              # Main plugin class
│   ├── Core/
│   │   ├── Content/
│   │   │   ├── Campaign/                  # Campaign entity
│   │   │   ├── Pledge/                    # Pledge entity
│   │   │   └── Tier/                      # Price tier entity
│   │   ├── Checkout/
│   │   │   ├── Cart/                      # Cart processor
│   │   │   └── Order/                     # Order subscriber
│   │   └── ScheduledTask/                 # Campaign end task
│   ├── Service/
│   │   ├── CampaignService.php            # Business logic
│   │   └── CampaignMailService.php        # Email handling
│   ├── Storefront/
│   │   ├── Controller/                    # API endpoints
│   │   └── Page/                          # Page subscribers
│   ├── Migration/                         # Database migrations
│   └── Resources/
│       ├── config/
│       │   ├── config.xml                 # Plugin configuration
│       │   └── services.xml               # Service definitions
│       ├── views/                         # Twig templates
│       ├── snippet/                       # Translations
│       └── app/
│           ├── administration/            # Admin module
│           └── storefront/                # Storefront assets
```

## Technical Details

### Entities

- **Campaign**: Stores campaign configuration and status
- **Pledge**: Records customer pledges with quantities and amounts
- **Tier**: Defines price tiers for group-buy discounts

### Key Services

- **CampaignService**: Core business logic for campaign management
- **PreOrderCartProcessor**: Adjusts cart prices for pledges
- **CampaignEndTaskHandler**: Processes campaigns when they end
- **CampaignMailService**: Sends notification emails

### Scheduled Tasks

The plugin runs a scheduled task every hour to:
1. Check for ended campaigns
2. Determine success/failure based on targets
3. Capture payments (success) or issue refunds (failure)
4. Send notification emails

## API Endpoints

### Storefront API

- `POST /campaign/pledge` - Create a pledge
- `GET /campaign/{id}/status` - Get campaign status

## Events

The plugin dispatches/listens to:
- `ProductPageLoadedEvent` - Add campaign data to product pages
- `CheckoutOrderPlacedEvent` - Link orders to pledges
- `OrderStateMachineStateChangeEvent` - Handle payment state changes

## Troubleshooting

### Campaign not showing on product page
- Ensure campaign is active and within date range
- Check that product is correctly linked to campaign
- Clear cache: `bin/console cache:clear`

### Scheduled task not running
- Check message queue consumers are running
- Verify scheduled task is registered: `bin/console scheduled-task:list`
- Run manually: `bin/console scheduled-task:run`

### Emails not sending
- Verify mail configuration in Shopware settings
- Check mail templates are installed (run migrations)
- Review mail queue for errors

## Development

### Running Tests
```bash
composer test
```

### Building Assets
```bash
# Watch mode for development
cd src/Resources/app/administration
npm run watch

cd src/Resources/app/storefront
npm run watch
```

## Support

For issues or questions, please create an issue in the repository or contact support.

## License

MIT License - see LICENSE file for details

## Changelog

### Version 1.0.0
- Initial release
- Basic campaign management
- Deposit system
- Email notifications
- Admin interface
- Storefront widget