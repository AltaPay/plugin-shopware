<?php

namespace Wexo\AltaPay\Subscriber;

use Exception;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexo\AltaPay\Service\PaymentService;

class OrderStateChangeSubscriber implements EventSubscriberInterface
{


    public function __construct(
        protected readonly PaymentService               $paymentService,
        protected readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        protected readonly LoggerInterface              $logger,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order.state.completed' => 'onOrderStateComplete',
            'state_enter.order_delivery.state.returned' => 'onOrderDeliveryStateReturned',
        ];
    }

    public function onOrderStateComplete(OrderStateMachineStateChangeEvent $event): void
    {
        try {
            $context = $event->getContext();
            $order = $event->getOrder();

            if (!$order || empty($order->getCustomFields()[PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD] ?? null)) {
                return;
            }

            $altaPayTransaction = $this->getAltaPayTransaction($order);

            if ($altaPayTransaction &&
                (float)$altaPayTransaction->ReservedAmount > 0.0 &&
                (float)$altaPayTransaction->CapturedAmount < (float)$altaPayTransaction->ReservedAmount) {

                $response = $this->paymentService->captureReservation($order, $order->getSalesChannelId());
                $responseAsXml = new SimpleXMLElement($response->getBody()->getContents());

                if ((string)$responseAsXml->Body?->Result === "Success") {
                    $this->orderTransactionStateHandler->paid(
                        $order->getTransactions()->first()->getId(),
                        $context
                    );
                }
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    public function onOrderDeliveryStateReturned(OrderStateMachineStateChangeEvent $event): void
    {
        try {
            $context = $event->getContext();
            $order = $event->getOrder();

            if (!$order || empty($order->getCustomFields()[PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD] ?? null)) {
                return;
            }

            $returnedDelivery = null;

            foreach ($order->getDeliveries() as $delivery) {
                if ($delivery->getStateMachineState()?->getTechnicalName() === 'returned') {
                    $returnedDelivery = $delivery;
                    break;
                }
            }

            if (!$returnedDelivery) {
                return;
            }

            $altaPayTransaction = $this->getAltaPayTransaction($order);

            if ($altaPayTransaction && (float)$altaPayTransaction->CapturedAmount > 0.0 &&
                (float)$altaPayTransaction->RefundedAmount < (float)$altaPayTransaction->CapturedAmount) {

                $response = $this->paymentService->refundCapturedReservation(
                    $order->getCustomFields()[PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD],
                    $order->getSalesChannelId()
                );
                $responseAsXml = new SimpleXMLElement($response->getBody()->getContents());

                if ((string)$responseAsXml->Body?->Result === "Success") {
                    $this->orderTransactionStateHandler->refund(
                        $order->getTransactions()->first()->getId(),
                        $context
                    );
                }
            } elseif ((float)$altaPayTransaction->ReservedAmount > 0.0 &&
                (float)$altaPayTransaction->CapturedAmount === 0.0) {

                $response = $this->paymentService->releaseReservation(
                    $order->getCustomFields()[PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD],
                    $order->getSalesChannelId()
                );
                $responseAsXml = new SimpleXMLElement($response->getBody()->getContents());

                if ((string)$responseAsXml->Body?->Result === "Success") {
                    $this->orderTransactionStateHandler->cancel(
                        $order->getTransactions()->first()->getId(),
                        $context
                    );
                }
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * @param OrderEntity $order
     * @return SimpleXMLElement|null
     * @throws Exception
     */
    private function getAltaPayTransaction(OrderEntity $order): ?SimpleXMLElement
    {
        $transactionResponse = $this->paymentService->getTransaction($order, $order->getSalesChannelId());
        $transactionResponseAsXml = new SimpleXMLElement($transactionResponse->getBody()->getContents());

        return $transactionResponseAsXml->Body?->Transactions?->Transaction;
    }
}
