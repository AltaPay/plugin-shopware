const ApiService = Shopware.Classes.ApiService;
const { Application } = Shopware;

class AltaPayService extends ApiService {
    getPayments(orderId) {
        return this.httpClient.get(
            `/${this.getApiBasePath()}/payments?orderId=${orderId}`,
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => ApiService.handleResponse(response));
    };

    capture(orderId) {
        return this.httpClient.post(
            `/${this.getApiBasePath()}/capture`,
            {
                orderId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => ApiService.handleResponse(response));
    };

    refund(orderId) {
        return this.httpClient.post(
            `/${this.getApiBasePath()}/refund`,
            {
                orderId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => ApiService.handleResponse(response));
    };

    cancel(orderId) {
        return this.httpClient.post(
            `/${this.getApiBasePath()}/cancel`,
            {
                orderId
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then(response => ApiService.handleResponse(response));
    };
}

Application.addServiceProvider('altaPayService', (container) => {
    const initContainer = Application.getContainer('init');
    return new AltaPayService(initContainer.httpClient, container.loginService, 'altapay');
});
