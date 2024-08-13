import template from './sw-order-detail.html.twig';

Shopware.Component.override('sw-order-detail', {
    template,

    computed: {
        altaPayTransactionId() {
            return this.order?.customFields?.wexoAltaPayTransactionId;
        }
    }
})
