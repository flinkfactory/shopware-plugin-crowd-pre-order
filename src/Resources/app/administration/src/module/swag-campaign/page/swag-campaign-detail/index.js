// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/administration/src/module/swag-campaign/page/swag-campaign-detail/index.js
import template from './swag-campaign-detail.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('swag-campaign-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    data() {
        return {
            campaign: null,
            isLoading: false,
            processSuccess: false,
            repository: null,
            tiers: null,
            pledges: null
        };
    },

    computed: {
        ...mapPropertyErrors('campaign', [
            'title',
            'productId',
            'startDate',
            'endDate'
        ]),

        identifier() {
            return this.campaign ? this.campaign.title : '';
        },

        campaignRepository() {
            return this.repositoryFactory.create('swag_crowd_campaign');
        },

        tierRepository() {
            return this.repositoryFactory.create('swag_crowd_tier');
        },

        pledgeRepository() {
            return this.repositoryFactory.create('swag_crowd_pledge');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.limit = 25;
            return criteria;
        },

        tierColumns() {
            return [{
                property: 'thresholdQuantity',
                dataIndex: 'thresholdQuantity',
                label: this.$tc('swag-campaign.detail.tierThreshold'),
                inlineEdit: 'number',
                allowResize: true
            }, {
                property: 'price',
                dataIndex: 'price',
                label: this.$tc('swag-campaign.detail.tierPrice'),
                inlineEdit: 'number',
                allowResize: true
            }];
        },

        pledgeColumns() {
            return [{
                property: 'customer.firstName',
                dataIndex: 'customer.firstName',
                label: this.$tc('swag-campaign.detail.pledgeCustomer'),
                allowResize: true
            }, {
                property: 'quantity',
                dataIndex: 'quantity',
                label: this.$tc('swag-campaign.detail.pledgeQuantity'),
                allowResize: true
            }, {
                property: 'pledgeAmount',
                dataIndex: 'pledgeAmount',
                label: this.$tc('swag-campaign.detail.pledgeAmount'),
                allowResize: true
            }, {
                property: 'createdAt',
                dataIndex: 'createdAt',
                label: this.$tc('swag-campaign.detail.pledgeDate'),
                allowResize: true
            }];
        },

        statusOptions() {
            return [{
                value: 'draft',
                label: this.$tc('swag-campaign.status.draft')
            }, {
                value: 'open',
                label: this.$tc('swag-campaign.status.open')
            }, {
                value: 'success',
                label: this.$tc('swag-campaign.status.success')
            }, {
                value: 'failed',
                label: this.$tc('swag-campaign.status.failed')
            }];
        }
    },

    created() {
        this.repository = this.campaignRepository;
        this.getCampaign();
        this.loadTiers();
        this.loadPledges();
    },

    methods: {
        getCampaign() {
            this.isLoading = true;

            this.repository
                .get(this.$route.params.id, Shopware.Context.api)
                .then((entity) => {
                    this.campaign = entity;
                    this.isLoading = false;
                })
                .catch(() => {
                    this.isLoading = false;
                });
        },

        loadTiers() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('campaignId', this.$route.params.id));
            criteria.addSorting(Criteria.sort('thresholdQuantity', 'ASC'));

            this.tierRepository
                .search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.tiers = result;
                });
        },

        loadPledges() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('campaignId', this.$route.params.id));
            criteria.addAssociation('customer');
            criteria.addAssociation('order');
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            this.pledgeRepository
                .search(criteria, Shopware.Context.api)
                .then((result) => {
                    this.pledges = result;
                });
        },

        onClickSave() {
            this.isLoading = true;

            this.repository
                .save(this.campaign, Shopware.Context.api)
                .then(() => {
                    this.getCampaign();
                    this.isLoading = false;
                    this.processSuccess = true;
                    this.createNotificationSuccess({
                        title: this.$tc('swag-campaign.detail.titleSaveSuccess'),
                        message: this.$tc('swag-campaign.detail.messageSaveSuccess')
                    });
                })
                .catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: this.$tc('swag-campaign.detail.titleSaveError'),
                        message: exception
                    });
                });
        },

        saveFinish() {
            this.processSuccess = false;
        },

        onAddTier() {
            const newTier = this.tierRepository.create(Shopware.Context.api);
            newTier.campaignId = this.campaign.id;
            newTier.thresholdQuantity = 0;
            newTier.price = 0;

            this.tiers.add(newTier);
        },

        onDeleteTier(tier) {
            this.tiers.remove(tier.id);
        },

        onSaveTiers() {
            this.tierRepository
                .saveAll(this.tiers, Shopware.Context.api)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('swag-campaign.detail.titleSaveSuccess'),
                        message: this.$tc('swag-campaign.detail.messageTiersSaved')
                    });
                    this.loadTiers();
                })
                .catch((exception) => {
                    this.createNotificationError({
                        title: this.$tc('swag-campaign.detail.titleSaveError'),
                        message: exception
                    });
                });
        }
    }
});