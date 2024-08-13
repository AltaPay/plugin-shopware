<?php

namespace Wexo\AltaPay\Subscriber;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexo\AltaPay\Service\PaymentService;
use Wexo\AltaPay\WexoAltaPay;

class PaymentMethodSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected EntityRepository $paymentMethodRepository,
        protected PluginIdProvider $pluginIdProvider
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_WRITTEN_EVENT => 'associateAltaPayPaymentMethod',
        ];
    }

    public function associateAltaPayPaymentMethod(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->hasState('altaPay')) {
            return;
        }

        $event->getContext()->addState('altaPay');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $event->getIds()));

        $paymentMethods = $this->paymentMethodRepository->search($criteria, $event->getContext())->getEntities();

        $paymentMethodsPayload = [];

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $payload = [];

            $payload['id'] = $paymentMethod->getId();
            if ($paymentMethod->getCustomFieldsValue('wexoAltaPayTerminalId')) {
                $payload['handlerIdentifier'] = PaymentService::class;
                $payload['pluginId'] = $this->pluginIdProvider->getPluginIdByBaseClass(
                    WexoAltaPay::class,
                    $event->getContext()
                );
            } else {
                if ($paymentMethod->getHandlerIdentifier() === PaymentService::class) {
                    $payload['handlerIdentifier'] = DefaultPayment::class;
                    $payload['pluginId'] = null;
                }
            }

            $paymentMethodsPayload[] = $payload;
        }

        $this->paymentMethodRepository->upsert($paymentMethodsPayload, $event->getContext());
    }
}
