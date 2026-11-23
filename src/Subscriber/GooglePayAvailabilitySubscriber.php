<?php declare(strict_types=1);

namespace Wexo\AltaPay\Subscriber;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexo\AltaPay\Service\PaymentService;

/**
 * Gives the confirm page the Google Pay methods and their config.
 */
class GooglePayAvailabilitySubscriber implements EventSubscriberInterface
{
    public const EXTENSION_NAME = 'altapayGooglePay';

    public function __construct(
        private readonly PaymentService $paymentService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'addGooglePayMethods',
        ];
    }

    public function addGooglePayMethods(CheckoutConfirmPageLoadedEvent $event): void
    {
        $event->getPage()->addExtension(
            self::EXTENSION_NAME,
            new ArrayStruct([
                'methods' => $this->paymentService->getGooglePayMethods(
                    $event->getPage()->getPaymentMethods() ?? [],
                    $event->getSalesChannelContext()
                ),
            ])
        );
    }
}
