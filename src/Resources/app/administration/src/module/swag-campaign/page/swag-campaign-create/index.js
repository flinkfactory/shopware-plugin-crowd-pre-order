// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/administration/src/module/swag-campaign/page/swag-campaign-create/index.js
import template from './swag-campaign-create.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('swag-campaign-create', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            campaign: null,
            isLoading: false,
            processSuccess: false,
            repository: null
        };
    },

    computed: {
        campaignRepository() {
            return this.repositoryFactory.create('swag_crowd_campaign');
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.limit = 25;
            return criteria;
        }
    },

    created() {
        this.repository = this.campaignRepository;
        this.campaign = this.createNewCampaign();
    },

    methods: {
        createNewCampaign() {
            const campaign = this.repository.create(Shopware.Context.api);

            // Set defaults
            campaign.active = false;
            campaign.status = 'draft';
            campaign.currentQuantity = 0;
            campaign.currentRevenue = 0;
            campaign.startDate = new Date();

            // Set end date to 30 days from now
            const endDate = new Date();
            endDate.setDate(endDate.getDate() + 30);
            campaign.endDate = endDate;

            return campaign;
        },

        saveFinish() {
            this.processSuccess = false;
        },

        onSave() {
            this.isLoading = true;

            this.repository
                .save(this.campaign, Shopware.Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.processSuccess = true;

                    this.$router.push({
                        name: 'swag.campaign.detail',
                        params: { id: this.campaign.id }
                    });
                })
                .catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: this.$tc('swag-campaign.create.errorTitle'),
                        message: exception
                    });
                });
        }
    }
});