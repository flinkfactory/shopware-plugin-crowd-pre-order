// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/administration/src/module/swag-campaign/page/swag-campaign-list/index.js
import template from './swag-campaign-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('swag-campaign-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            campaigns: null,
            isLoading: true,
            sortBy: 'createdAt',
            sortDirection: 'DESC'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        campaignRepository() {
            return this.repositoryFactory.create('swag_crowd_campaign');
        },

        campaignColumns() {
            return [{
                property: 'title',
                dataIndex: 'title',
                label: this.$tc('swag-campaign.list.columnTitle'),
                routerLink: 'swag.campaign.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'product.name',
                dataIndex: 'product.name',
                label: this.$tc('swag-campaign.list.columnProduct'),
                allowResize: true
            }, {
                property: 'startDate',
                dataIndex: 'startDate',
                label: this.$tc('swag-campaign.list.columnStartDate'),
                allowResize: true
            }, {
                property: 'endDate',
                dataIndex: 'endDate',
                label: this.$tc('swag-campaign.list.columnEndDate'),
                allowResize: true
            }, {
                property: 'targetQuantity',
                dataIndex: 'targetQuantity',
                label: this.$tc('swag-campaign.list.columnTarget'),
                allowResize: true
            }, {
                property: 'currentQuantity',
                dataIndex: 'currentQuantity',
                label: this.$tc('swag-campaign.list.columnCurrent'),
                allowResize: true
            }, {
                property: 'status',
                dataIndex: 'status',
                label: this.$tc('swag-campaign.list.columnStatus'),
                allowResize: true
            }, {
                property: 'active',
                dataIndex: 'active',
                label: this.$tc('swag-campaign.list.columnActive'),
                allowResize: true
            }];
        },

        campaignCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('product');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            return criteria;
        }
    },

    methods: {
        getList() {
            this.isLoading = true;

            this.campaignRepository
                .search(this.campaignCriteria, Shopware.Context.api)
                .then((result) => {
                    this.campaigns = result;
                    this.isLoading = false;
                });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.getList();
        },

        onRefresh() {
            this.getList();
        }
    }
});