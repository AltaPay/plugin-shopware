import "./page/sw-order-detail";
import "./view/sw-order-detail-altapay";
import "./services/altaPay.service";

Shopware.Module.register('wexo-altapay', {
    routeMiddleware(next, currentRoute) {
        const customRouteName = 'sw.order.detail.altaPay';

        if (
            currentRoute.name === 'sw.order.detail'
            && currentRoute.children.every((currentRoute) => currentRoute.name !== customRouteName)
        ) {
            currentRoute.children.push({
                name: customRouteName,
                path: '/sw/order/detail/:id/altapay',
                component: 'sw-order-detail-altapay',
                meta: {
                    parentPath: 'sw.order.index'
                }
            });
        }
        next(currentRoute);
    }
})

Shopware.Locale.extend('da-DK', {
    'altaPay': {
        'tabDetails': 'AltaPay',
        'capture': 'Hæv',
        'refund': 'Refunder',
        'cancel': 'Annuller'
    }
})
Shopware.Locale.extend('en-GB', {
    'altaPay': {
        'tabDetails': 'AltaPay',
        'capture': 'Capture',
        'refund': 'Refund',
        'cancel': 'Cancel'
    }
})
Shopware.Locale.extend('de-DE', {
    'altaPay': {
        'tabDetails': 'AltaPay',
        'capture': 'Einfangen',
        'refund': 'Rückerstattung',
        'cancel': 'Annullieren'
    }
})
