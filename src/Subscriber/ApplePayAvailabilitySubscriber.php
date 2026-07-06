<?php declare(strict_types=1);

namespace Wexo\AltaPay\Subscriber;

use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexo\AltaPay\Service\PaymentService;

/**
 * Hides Apple Pay payment methods from the checkout confirm page when
 * the browser is not Safari (Apple Pay is only available in Safari).
 */
class ApplePayAvailabilitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'hideApplePayForNonSafari',
        ];
    }

    public function hideApplePayForNonSafari(CheckoutConfirmPageLoadedEvent $event): void
    {
        $request   = $this->requestStack->getCurrentRequest();
        $userAgent = $request ? $request->headers->get('User-Agent', '') : '';

        /* Same regex as AltaPay Magento2 plugin: exclude Chrome and Android browsers */
        if (preg_match('/^((?!chrome|android).)*safari/i', $userAgent)) {
            return;
        }

        $paymentMethods = $event->getPage()->getPaymentMethods();
        if (!$paymentMethods) {
            return;
        }

        $filtered = $paymentMethods->filter(function ($method) {
            $customFields = $method->getTranslated()['customFields'] ?? $method->getCustomFields() ?? [];
            return empty($customFields[PaymentService::ALTAPAY_IS_APPLE_PAY_CUSTOM_FIELD]);
        });

        $event->getPage()->setPaymentMethods($filtered);
    }
}
