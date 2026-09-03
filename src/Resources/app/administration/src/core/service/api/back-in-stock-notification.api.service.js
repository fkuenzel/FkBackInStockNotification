/**
 * Thin admin API client for the plugin's custom actions (statistics, cron state,
 * manual send, log viewer/download and audited bulk delete). Auth headers are
 * handled by the Shopware ApiService base class.
 */
const ApiService = Shopware.Classes.ApiService;

class BackInStockNotificationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'back-in-stock-notification') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'backInStockNotificationApiService';
    }

    getStatistics() {
        return this.httpClient
            .get('_action/back-in-stock-notification/statistics', { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getCronState() {
        return this.httpClient
            .get('_action/back-in-stock-notification/cron-state', { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    sendNow() {
        return this.httpClient
            .post('_action/back-in-stock-notification/send-now', {}, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    getLogs(params = {}) {
        return this.httpClient
            .get('_action/back-in-stock-notification/logs', { params, headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    downloadLogs(params = {}) {
        return this.httpClient
            .get('_action/back-in-stock-notification/logs/download', {
                params,
                responseType: 'blob',
                headers: this.getBasicHeaders(),
            })
            .then((response) => response.data);
    }

    bulkDelete(ids) {
        return this.httpClient
            .post('_action/back-in-stock-notification/delete', { ids }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

Shopware.Application.addServiceProvider('backInStockNotificationApiService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    return new BackInStockNotificationApiService(initContainer.httpClient, container.loginService);
});

export default BackInStockNotificationApiService;
