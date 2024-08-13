<?php

namespace Wexo\AltaPay\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRouteResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Wexo\AltaPay\WexoAltaPay;

class CartOrderRouteDecorator extends AbstractCartOrderRoute
{

    public function __construct(
        protected AbstractCartOrderRoute $decoratedService,
        protected AbstractCartPersister $cartPersister,
        protected EntityRepository $orderRepository,
        protected EntityRepository $orderTransactionRepository,
        protected PluginIdProvider $pluginIdProvider,
    ) {
    }

    public function getDecorated(): AbstractCartOrderRoute
    {
        return $this->decoratedService->getDecorated();
    }

    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): CartOrderRouteResponse
    {
        $originalCart = $this->cartPersister->load($context->getToken(), $context);

        $response = $this->decoratedService->order($cart, $context, $data);

        $this->restoreCart($originalCart, $response->getOrder(), $context);

        return $response;
    }

    protected function restoreCart(Cart $cart, OrderEntity $orderEntity, SalesChannelContext $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderId', $orderEntity->getId()))
            ->addAssociation('paymentMethod');


        /** @var OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context->getContext())->first();

        if ($orderTransaction) {
            /** @var PaymentMethodEntity $paymentMethod */
            $paymentMethod = $orderTransaction->getPaymentMethod();

            $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(
                WexoAltaPay::class,
                $context->getContext()
            );

            /**
             * If a AltaPay payment method was used we restore the cart
             * and the cart will be cleared in PaymentService::transactionCallback()
             */
            if ($paymentMethod->getPluginId() === $pluginId) {
                $this->cartPersister->save($cart, $context);

                $this->orderRepository->update([[
                    'id' => $orderEntity->getId(),
                    'customFields' => [
                        WexoAltaPay::ALTAPAY_CART_TOKEN => $cart->getToken(),
                    ],
                ]], $context->getContext());
            }
        }
    }
}
