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

            if ($altaPayTransaction && (float)$altaPayTransaction->ReservedAmount > 0.0) {

                $orderTotal = $order->getAmountTotal();
                $capturedAmount = (float)$altaPayTransaction->CapturedAmount;

                if ($capturedAmount > 0) {
                  $this->logger->error("Could not capture automatically. Manual capture is required for the order: " . $order->getId());
                  return;
                }

                // Calculate the remaining amount that can be captured
                $remainingAmount = $orderTotal - $capturedAmount;

                // Ensure the remaining amount is not negative and less than or equal to the reserved amount
                if ($remainingAmount > 0.0 && $remainingAmount <= (float)$altaPayTransaction->ReservedAmount) {

                    $response = $this->paymentService->captureReservation($order, $order->getSalesChannelId(), $remainingAmount);
                    $responseAsXml = new SimpleXMLElement($response->getBody()->getContents());

                    if ((string)$responseAsXml->Body?->Result === "Success") {
                        $this->orderTransactionStateHandler->paid(
                            $order->getTransactions()->first()->getId(),
                            $context
                        );
                    } else {
                        $this->logger->error("Capture failed for Order ID: " . $order->getId());
                    }
                } else {
                    $this->logger->error(
                        "Invalid remaining amount for capture. Order ID: {$order->getId()}, " .
                        "Remaining Amount: {$remainingAmount}, Reserved Amount: {$altaPayTransaction->ReservedAmount}"
                    );
                }
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
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
                } else {
                    $this->logger->error("Refund failed for Order ID: {$order->getId()}, due to the status: " . (string)$responseAsXml->Body?->Result);
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
                } else {
                    $this->logger->error("Release reservation failed for Order ID: {$order->getId()}");
                }
            } else {
                $this->logger->warning(
                    "Transaction state not valid for refund or release. Order ID: {$order->getId()}, " .
                    "Captured Amount: {$altaPayTransaction->CapturedAmount}, " .
                    "Refunded Amount: {$altaPayTransaction->RefundedAmount}, " .
                    "Reserved Amount: {$altaPayTransaction->ReservedAmount}"
                );
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
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
