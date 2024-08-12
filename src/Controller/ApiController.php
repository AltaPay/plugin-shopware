<?php declare(strict_types=1);

namespace Wexo\AltaPay\Controller;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Wexo\AltaPay\Service\PaymentService;

#[Route(defaults: ['_routeScope' => ['administration']])]
class ApiController extends AbstractController
{
    public function __construct(
        protected readonly PaymentService   $paymentService,
        protected readonly EntityRepository $orderRepository,
        protected readonly OrderTransactionStateHandler $orderTransactionStateHandler,
    ) {
    }

    #[Route(path: '/api/altapay/payments', name: 'api.altapay.transaction', methods: ['GET'])]
    public function getPayments(Request $request): JsonApiResponse|Response
    {
        $orderId = $request->get('orderId');
        $order = $this->orderRepository->search(new Criteria([$orderId]), Context::createDefaultContext())->first();
        if (!$order) {
            return new Response(status: 400);
        }
        /** @var $order OrderEntity */
        $response = $this->paymentService->getTransaction($order, $order->getSalesChannelId());
        $responseAsXml = new \SimpleXMLElement($response->getBody()->getContents());
        return new JsonApiResponse(json_encode($responseAsXml));
    }

    #[Route(path: '/api/altapay/capture', name: 'api.altapay.capture', methods: ['POST'])]
    public function capture(Request $request): JsonApiResponse|Response
    {
        $orderId = $request->get('orderId');
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions')
            ->addSorting(new FieldSorting('transactions.createdAt', FieldSorting::DESCENDING));
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return new Response(status: 400);
        }
        /** @var $order OrderEntity */
        $response = $this->paymentService->captureReservation($order, $order->getSalesChannelId());
        $responseAsXml = new \SimpleXMLElement($response->getBody()->getContents());
        if ((string)$responseAsXml->Body?->Result === "Success") {
            $this->orderTransactionStateHandler->paid(
                $order->getTransactions()->first()->getId(), // todo get right transaction
                $context
            );
        }
        return new JsonApiResponse(json_encode($responseAsXml));
    }

    #[Route(path: '/api/altapay/refund', name: 'api.altapay.refund', methods: ['POST'])]
    public function refund(Request $request): JsonApiResponse|Response
    {
        $orderId = $request->get('orderId');
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions')
            ->addSorting(new FieldSorting('transactions.createdAt', FieldSorting::DESCENDING));
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return new Response(status: 400);
        }
        /** @var $order OrderEntity */
        $response = $this->paymentService->refundCapturedReservation(
            $order->getCustomFields()[PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD],
            $order->getSalesChannelId()
        );
        $responseAsXml = new \SimpleXMLElement($response->getBody()->getContents());
        if ((string)$responseAsXml->Body?->Result === "Success") {
            $this->orderTransactionStateHandler->refund(
                $order->getTransactions()->first()->getId(), // todo get right transaction
                $context
            );
        }
        return new JsonApiResponse(json_encode($responseAsXml));
    }

    #[Route(path: '/api/altapay/cancel', name: 'api.altapay.cancel', methods: ['POST'])]
    public function cancel(Request $request): JsonApiResponse|Response
    {
        $orderId = $request->get('orderId');
        $context = Context::createDefaultContext();
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions')
            ->addSorting(new FieldSorting('transactions.createdAt', FieldSorting::DESCENDING));
        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order) {
            return new Response(status: 400);
        }
        /** @var $order OrderEntity */
        $response = $this->paymentService->releaseReservation(
            $order->getCustomFields()[PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD],
            $order->getSalesChannelId()
        );
        $responseAsXml = new \SimpleXMLElement($response->getBody()->getContents());
        if ((string)$responseAsXml->Body?->Result === "Success") {
            $this->orderTransactionStateHandler->cancel(
                $order->getTransactions()->first()->getId(), // todo get right transaction
                $context
            );
        }
        return new JsonApiResponse(json_encode($responseAsXml));
    }
}
