<?php declare(strict_types=1);

namespace Wexo\AltaPay\Controller;

use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexo\AltaPay\Service\PaymentService;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CallbackController extends StorefrontController
{
    public function __construct(
        protected readonly PaymentService $paymentService,
        protected readonly EntityRepository $orderRepository,
        protected readonly LoggerInterface $logger,
        protected readonly RouterInterface $router,
        protected readonly TranslatorInterface $translator,
        protected readonly SystemConfigService $systemConfigService,
        protected readonly EntityRepository $mediaRepository
    ) {
    }

    #[Route(
        path: '/altapay/getStyling',
        name: 'altapay.gateway.styling',
        defaults: ['auth_required' => false],
        methods: ['POST']
    )]
    public function getTerminalStyling(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $orderNumber = $request->get('shop_orderid');

        $criteria = new Criteria();
        $criteria->addAssociation('currency');
        $criteria->addAssociation('language');
        $criteria->addAssociation('language.locale');
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        if (!$order) {
            throw CartException::orderNotFound($orderNumber);
        }

        $mediaId = $this->systemConfigService->get(
            'WexoAltaPay.config.paymentGatewayMedia',
            $salesChannelContext->getSalesChannelId()
        );

        /** @var MediaEntity|null $media */
        $media = !empty($mediaId) ? $this->mediaRepository->search(
            new Criteria([$mediaId]),
            $salesChannelContext->getContext()
        )->get($mediaId) : null;

        $data = [
            'shopName' => $salesChannelContext->getSalesChannel()->getName(),
            'shopOrderId' => $orderNumber,
            'totalAmount' => $request->get('amount'),
            'isoCode' => $order->getCurrency()?->getIsoCode() ?? '',
            'symbol' => $order->getCurrency()?->getSymbol() ?? '',
            'languageCode' => $request->get('language'),
            'mediaUrl' => $media?->getUrl(),
            'title' => $this->translator->trans('altapay.gateway.title'),
            'orderNumberSnippet' => $this->translator->trans('altapay.gateway.orderNumber'),
            'totalPriceSnippet' => $this->translator->trans('altapay.gateway.totalPrice'),
            'context' => $salesChannelContext,
            'order' => $order,
        ];

        return $this->render('@WexoAltaPay/gateway/index.html.twig', ['gatewayData' => $data]);
    }

    #[Route(
        path: '/altapay/getError',
        name: 'altapay.gateway.error',
        defaults: ['auth_required' => false],
        methods: ['POST']
    )]
    public function getError(Request $request): Response
    {
        $this->logger->error(json_encode($request->getPayload()));

        $returnUrl = $this
            ->router
            ->generate(
                'payment.finalize.transaction',
                [
                    '_sw_payment_token' => $request->get('_sw_payment_token'),
                    'xml' => $request->get('xml')
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        return new RedirectResponse($returnUrl);
    }

    #[Route(
        path: '/altapay/callback/notification',
        defaults: ['auth_required' => false],
        methods: ['POST']
    )]
    public function notification(Request $request, SalesChannelContext $salesChannelContext)
    {
        if (!IpUtils::checkIp($request->getClientIp(), PaymentService::ALTAPAY_IP_ADDRESS_SET)) {
            return new Response('Invalid request', 400);
        }
        try {
            $result = new SimpleXMLElement($request->get('xml'));
            $orderNumber = (string)$result?->APIResponse?->Body?->Transactions?->Transaction[0]?->ShopOrderId;
            $paymentId = (string)$result?->APIResponse?->Body?->Transactions?->Transaction[0]?->PaymentId;
            if (!$orderNumber) {
                throw new Exception();
            }
        } catch (Exception) {
            return new Response('Invalid request', 400);
        }
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $criteria->getAssociation('transactions')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        if (!$order ||
            ($order->getCustomFields()[PaymentService::ALTAPAY_PAYMENT_ID_CUSTOM_FIELD] ?? null) !== $paymentId) {
            return new Response('Invalid request', 400);
        }
        $transaction = $order->getTransactions()->first();
        $this->paymentService->transactionCallback($result, $order, $transaction, $salesChannelContext);
        return new Response(null, 204);
    }
}
