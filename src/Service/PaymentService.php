<?php declare(strict_types=1);

namespace Wexo\AltaPay\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Wexo\AltaPay\WexoAltaPay;

/**
 * Represents the Payment Method in Shopware,
 * and contains functions for communicating with AltaPay via API.
 */
class PaymentService implements AsynchronousPaymentHandlerInterface
{
    public const ALTAPAY_PAYMENT_ID_CUSTOM_FIELD = "wexoAltaPayPaymentId";
    public const ALTAPAY_TERMINAL_ID_CUSTOM_FIELD = "wexoAltaPayTerminalId";
    public const ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD = "wexoAltaPayTransactionId";
    public const ALTAPAY_TRANSACTION_PAYMENT_SCHEME_NAME_CUSTOM_FIELD = "wexoAltapayTransactionPaymentSchemeName";
    public const ALTAPAY_TRANSACTION_PAYMENT_NATURE_CUSTOM_FIELD = "wexoAltapayTransactionPaymentNature";
    public const ALTAPAY_IP_ADDRESS_SET = ["185.206.120.0/24", "2a10:a200::/29"];

    public function __construct(
        protected readonly SystemConfigService $systemConfigService,
        protected readonly OrderTransactionStateHandler $orderTransactionStateHandler,
        protected readonly EntityRepository $orderRepository,
        protected readonly EntityRepository $orderAddressRepository,
        protected readonly RouterInterface $router,
        protected readonly EntityRepository $languageRepository,
        protected readonly AbstractCartPersister $cartPersister
    ) {
    }

    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        $order = $transaction->getOrder();
        $paymentMethod = $transaction->getOrderTransaction()->getPaymentMethod();
        $terminal = $paymentMethod->getTranslated()['customFields'][self::ALTAPAY_TERMINAL_ID_CUSTOM_FIELD];

        try {
            $altaPayResponse = $this->createPaymentRequest(
                $order,
                $transaction->getReturnUrl(),
                $salesChannelContext,
                $terminal
            );
        } catch (GuzzleException $e) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                "Request error when creating payment request @ AltaPay",
                $e
            );
        }
        if (!$altaPayResponse->Body->Result) {
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                (string)$altaPayResponse->Header->ErrorMessage,
            );
        }
        $order->changeCustomFields([
            self::ALTAPAY_PAYMENT_ID_CUSTOM_FIELD => (string)$altaPayResponse->Body->PaymentRequestId
        ]);
        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'customFields' => $order->getCustomFields()
            ]
        ], $salesChannelContext->getContext());
        return new RedirectResponse((string)$altaPayResponse->Body->Url, 302, [
            // In case someone wants to embed a payment window
            'X-Dynamic-JavaScript-Url' => (string)$altaPayResponse->DynamicJavascriptUrl
        ]);
    }

    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $this->transactionCallback(
            new SimpleXMLElement($request->get('xml')),
            $transaction->getOrder(),
            $transaction->getOrderTransaction(),
            $salesChannelContext
        );
    }

    public function transactionCallback(
        SimpleXMLElement $result,
        OrderEntity $order,
        OrderTransactionEntity $transaction,
        SalesChannelContext $salesChannelContext
    ): void {
        switch ((string)$result->Body?->Result) {
            case "Success":
                // Delete cart when either customer or AltaPay reaches this page.
                $this->cartPersister->delete(
                    $order->getCustomFieldsValue(field: WexoAltaPay::ALTAPAY_CART_TOKEN),
                    $salesChannelContext
                );

                if ($this->systemConfigService
                        ->getBool('WexoAltaPay.config.keepOrderOpen', $salesChannelContext->getSalesChannelId())
                    ||
                    $transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_OPEN) {
                    break;
                }
                $this->orderTransactionStateHandler->process(
                    $transaction->getId(),
                    $salesChannelContext->getContext()
                );

                if ((string)$result->Body->Transactions->Transaction->TransactionStatus === 'preauth'
                    || (string)$result->Body->Transactions->Transaction->PaymentNature === 'Invoice'
                ) {
                    $this->orderTransactionStateHandler->authorize(
                        $transaction->getId(),
                        $salesChannelContext->getContext()
                    );
                }

                break;
            case "Cancel":
                throw new CustomerCanceledAsyncPaymentException(
                    $transaction->getId(),
                    "Transaction was cancelled in AltaPay"
                );
            // From the docs it seems like the body can potentially be empty, in which case the header still has data.
            case "":
            case "Error":
            case "Fail":
            default:
                throw new AsyncPaymentFinalizeException(
                    $transaction->getId(),
                    (string)$result->Body?->MerchantErrorMessage
                    ?? (string)$result->APIResponse?->Header?->ErrorMessage
                );
        }
        $altaPayTransactionId = (string)$result->Body->Transactions->Transaction->TransactionId;
        $altaPayPaymentSchemeName= (string)$result->Body->Transactions->Transaction->PaymentSchemeName;
        $altaPayPaymentNature= (string)$result->Body->Transactions->Transaction->PaymentNature;

        $order->changeCustomFields([
            self::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD => $altaPayTransactionId,
            self::ALTAPAY_TRANSACTION_PAYMENT_SCHEME_NAME_CUSTOM_FIELD => $altaPayPaymentSchemeName,
            self::ALTAPAY_TRANSACTION_PAYMENT_NATURE_CUSTOM_FIELD => $altaPayPaymentNature,
        ]);
        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'customFields' => $order->getCustomFields()
            ]
        ], $salesChannelContext->getContext());
    }

    public function getAltaPayClient(string $salesChannelId): Client
    {
        $paymentEnvironment = $this->systemConfigService->get('WexoAltaPay.config.paymentEnvironment', $salesChannelId);
        $shopName = $this->systemConfigService->get('WexoAltaPay.config.shopName', $salesChannelId);
        $username = $this->systemConfigService->get('WexoAltaPay.config.username', $salesChannelId);
        $password = $this->systemConfigService->get('WexoAltaPay.config.password', $salesChannelId);
        return new Client([
            'base_uri' => str_replace('$PLACEHOLDER$', $shopName, $paymentEnvironment),
            'auth' => [
                $username,
                $password
            ]
        ]);
    }

    /**
     * Escape hatch that can be overridden for custom line items.
     */
    public function getUnknownLineItemFormat(OrderEntity $order, OrderLineItemEntity $lineItem): array
    {
        return [
            'description' => $lineItem->getLabel(),
            'itemId' => $lineItem->getId(),
            'quantity' => $lineItem->getQuantity(),
            'unitPrice' => $lineItem->getPrice()?->getUnitPrice() ?? 0.0,
            'taxAmount' => $lineItem->getPrice()?->getCalculatedTaxes()->getAmount() ?? 0.0,
            'discount' => $lineItem->getPrice()->getListPrice()?->getDiscount() ?? 0.0,
            'goodsType' => match ($lineItem->getType()) {
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                LineItem::CONTAINER_LINE_ITEM,
                LineItem::CUSTOM_LINE_ITEM_TYPE => 'item',
                LineItem::CREDIT_LINE_ITEM_TYPE,
                LineItem::DISCOUNT_LINE_ITEM,
                LineItem::PROMOTION_LINE_ITEM_TYPE => 'handling',
            }
        ];
    }

    /**
     * @see https://documentation.altapay.com/Content/Ecom/API/API%20Methods/createPaymentRequest.htm
     * @throws GuzzleException
     */
    public function createPaymentRequest(
        OrderEntity $order,
        string      $returnUrl,
        SalesChannelContext $context,
        string      $terminal
    ): SimpleXMLElement {
        $orderLines = [];
        foreach ($order->getLineItems() as $lineItem) {
            $unitTaxRate = $lineItem->getPrice()?->getCalculatedTaxes()->getAmount() / $lineItem->getQuantity();

            $unitPrice = ($lineItem->getPrice()?->getUnitPrice() ?? 0.0) - ($unitTaxRate ?? 0.0);
            $taxAmount = $lineItem->getPrice()?->getCalculatedTaxes()->getAmount() ?? 0.0;

            $discount = $lineItem->getPrice()?->getListPrice()?->getDiscount() ?? 0.0;

            if ($discount != 0.0) {
                $discount = ($taxAmount + $unitPrice) * (abs($discount) / 100);
            }

            $orderLines[] = match ($lineItem->getType()) {
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                LineItem::CONTAINER_LINE_ITEM,
                LineItem::CUSTOM_LINE_ITEM_TYPE,
                LineItem::CREDIT_LINE_ITEM_TYPE,
                LineItem::DISCOUNT_LINE_ITEM,
                LineItem::PROMOTION_LINE_ITEM_TYPE => [
                    'description' => $lineItem->getLabel(),
                    'itemId' => $lineItem->getId(),
                    'quantity' => $lineItem->getQuantity(),
                    'unitPrice' => $unitPrice,
                    'taxAmount' => $taxAmount,
                    'discount' => $discount,
                    'goodsType' => match ($lineItem->getType()) {
                        LineItem::PRODUCT_LINE_ITEM_TYPE,
                        LineItem::CONTAINER_LINE_ITEM,
                        LineItem::CUSTOM_LINE_ITEM_TYPE => 'item',
                        LineItem::CREDIT_LINE_ITEM_TYPE,
                        LineItem::DISCOUNT_LINE_ITEM,
                        LineItem::PROMOTION_LINE_ITEM_TYPE => 'handling',
                    }
                ],
                default => $this->getUnknownLineItemFormat($order, $lineItem)
            };
        }
        foreach ($order->getDeliveries() as $delivery) {
            $netUnitPrice = $delivery->getShippingCosts()->getUnitPrice()
                - $delivery->getShippingCosts()->getCalculatedTaxes()->getAmount();

            $taxAmount = $delivery->getShippingCosts()->getCalculatedTaxes()->getAmount();

            $discount = $delivery->getShippingCosts()->getListPrice()?->getDiscount() ?? 0.0;

            if ($discount != 0.0) {
                $discount = ($taxAmount + $netUnitPrice) * (abs($discount) / 100);
            }

            $orderLines[] = [
                'description' => $delivery->getShippingMethod()?->getDescription() ?? 'Shipping',
                'itemId' => $delivery->getId(),
                'quantity' => 1,
                'unitPrice' => $netUnitPrice,
                'taxAmount' => $taxAmount,
                'discount' => $discount,
                'goodsType' => 'shipment'
            ];
        }

        $customer = $order->getOrderCustomer();

        $gatewayStyleUrl = $this->router->generate(
            name: 'altapay.gateway.styling',
            referenceType: UrlGeneratorInterface::ABSOLUTE_URL
        );

        parse_str(parse_url($returnUrl, PHP_URL_QUERY), $params);

        $gatewayErrorUrl = $this->router->generate(
            name: 'altapay.gateway.error',
            parameters: $params,
            referenceType: UrlGeneratorInterface::ABSOLUTE_URL
        );

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getDeliveries()->getShippingAddress()->first();

        if ($order->getLanguage()) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $order->getLanguage()->getId()));
            $criteria->addAssociation('locale');

            /** @var LanguageEntity $languageEntity */
            $languageEntity = $this->languageRepository->search(
                $criteria,
                $context->getContext()
            )->get($order->getLanguageId());
            $languageCode = substr($languageEntity->getLocale()->getCode(), 0, 2);
        }

        $salesChannelId = $context->getSalesChannelId();
        $response = $this->getAltaPayClient($salesChannelId)->request('POST', 'createPaymentRequest', [
            'form_params' => [
                'terminal' => $terminal,
                'language' => $languageCode ?? 'en',
                'shop_orderid' => $order->getOrderNumber(),
                'amount' => $order->getAmountTotal(),
                'currency' => $order->getCurrency()->getIsoCode(),
                'config' => [
                    'callback_ok' => $returnUrl,
                    'callback_fail' => $gatewayErrorUrl,
                    'callback_open' => $returnUrl,
                    'callback_form' => $gatewayStyleUrl,
                ],
                'orderLines' => $orderLines,
                'customer_info' => [
                    'username' => $customer->getCustomerNumber(),
                    'shipping_lastname' => $shippingAddress->getLastName(),
                    'shipping_firstname' => $shippingAddress->getFirstName(),
                    'shipping_address' => $shippingAddress->getStreet(),
                    'shipping_postal' => $shippingAddress->getZipcode(),
                    'shipping_region' => $shippingAddress->getCountryState()?->getName() ?? '',
                    'shipping_country' => $shippingAddress->getCountry()->getIso(),
                    'shipping_city' => $shippingAddress->getCity(),
                    'email' => $customer->getEmail(),
                    'customer_phone' => $billingAddress->getPhoneNumber() ?? '',
                    'birthdate' => $customer->getCustomer()->getBirthday()?->format('Y-m-d') ?? '',
                    'billing_lastname' => $billingAddress->getLastName(),
                    'billing_firstname' => $billingAddress->getFirstName(),
                    'billing_address' => $billingAddress->getStreet(),
                    'billing_city' => $billingAddress->getCity(),
                    'billing_region' => $billingAddress->getCountryState()?->getName() ?? '',
                    'billing_postal' => $billingAddress->getZipcode(),
                    'billing_country' => $billingAddress->getCountry()->getIso(),
                ],
            ]
        ]);
        return new SimpleXMLElement($response->getBody()->getContents());
    }

    public function getTransaction(OrderEntity $order, string $salesChannelId): ResponseInterface
    {
        return $this->getAltaPayClient($salesChannelId)->request('GET', 'payments', [
            'query' => [
                'transaction_id' => $order->getCustomFields()[self::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD],
                'shop_orderid' => $order->getOrderNumber(),
            ]
        ]);
    }

    /**
     * @see https://documentation.altapay.com/Content/Ecom/API/API%20Methods/captureReservation.htm
     */
    public function captureReservation(OrderEntity $order, string $salesChannelId): ResponseInterface
    {
        return $this->getAltaPayClient($salesChannelId)->request('POST', 'captureReservation', [
            'form_params' => [
                'amount' => $order->getAmountTotal(),
                'transaction_id' => $order->getCustomFields()[self::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD]
            ]
        ]);
    }

    /**
     * @see https://documentation.altapay.com/Content/Ecom/API/API%20Methods/releaseReservation.htm
     */
    public function releaseReservation(string $altaPayTransactionId, string $salesChannelId): ResponseInterface
    {
        return $this->getAltaPayClient($salesChannelId)->request('POST', 'releaseReservation', [
            'form_params' => [
                'transaction_id' => $altaPayTransactionId
            ]
        ]);
    }

    /**
     * @see https://documentation.altapay.com/Content/Ecom/API/API%20Methods/refundCapturedReservation.htm
     * @param float|null $amount If you do not set an amount, the full amount is refunded.
     *
     * @throws GuzzleException
     */
    public function refundCapturedReservation(
        string $altaPayTransactionId,
        string $salesChannelId,
        ?float $amount = null
    ): ResponseInterface {
        $params = ['transaction_id' => $altaPayTransactionId];
        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        return $this->getAltaPayClient($salesChannelId)->request('POST', 'refundCapturedReservation', [
            'form_params' => $params
        ]);
    }
}
