// ./custom/plugins/SwagCrowdPreOrder/src/Resources/app/administration/src/api/campaign.api.service.js
const { ApiService } = Shopware.Classes;

class CampaignApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'campaign') {
        super(httpClient, loginService, apiEndpoint);
    }

    /**
     * Get campaign statistics
     */
    getStatistics(campaignId) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/statistics/${campaignId}`, {
                headers: this.getBasicHeaders()
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Clone a campaign
     */
    cloneCampaign(campaignId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/clone/${campaignId}`, {}, {
                headers: this.getBasicHeaders()
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Export pledges to CSV
     */
    exportPledges(campaignId) {
        return this.httpClient
            .get(`${this.getApiBasePath()}/export-pledges/${campaignId}`, {
                headers: this.getBasicHeaders(),
                responseType: 'blob'
            })
            .then((response) => {
                return response.data;
            });
    }

    /**
     * Manually end a campaign
     */
    endCampaign(campaignId) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/end/${campaignId}`, {}, {
                headers: this.getBasicHeaders()
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get campaign analytics
     */
    getAnalytics(campaignId, dateFrom = null, dateTo = null) {
        const params = {};
        if (dateFrom) params.dateFrom = dateFrom;
        if (dateTo) params.dateTo = dateTo;

        return this.httpClient
            .get(`${this.getApiBasePath()}/analytics/${campaignId}`, {
                params,
                headers: this.getBasicHeaders()
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Send test email for campaign
     */
    sendTestEmail(campaignId, emailAddress) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/test-email/${campaignId}`,
                { email: emailAddress },
                { headers: this.getBasicHeaders() }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// Register the service
Shopware.Service().register('campaignApiService', () => {
    const initContainer = Shopware.Application.getContainer('init');
    return new CampaignApiService(
        initContainer.httpClient,
        Shopware.Service('loginService')
    );
});