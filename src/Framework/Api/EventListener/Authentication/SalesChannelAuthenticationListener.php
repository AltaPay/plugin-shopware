<?php declare(strict_types=1);

namespace Wexo\AltaPay\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RouteScopeCheckTrait;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use SimpleXMLElement;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wexo\AltaPay\Controller\CallbackController;

class SalesChannelAuthenticationListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    public function __construct(
        protected Connection $connection,
        protected RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'validateSalesChannelId',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_PRIORITY_AUTH_VALIDATE_PRE
            ],
        ];
    }

    /**
     * Listen for AltaPay callbacks within CallbackController.
     * -When using Frontends, headless etc. the current SalesChannelContext will be set as the callback Storefront
     * meaning that we might be getting a wrong salesChannelContext in our flow.
     *
     * This will set the salesChannelId based on the current order,
     * -which Shopware will generate a context for based on salesChannelId request attribute,
     * -and ensure we're getting a SalesChannelContext from the correct salesChannel and correct flow, config etc.
     *
     * @see \Shopware\Core\Framework\Api\EventListener\Authentication\SalesChannelAuthenticationListener::validateRequest
     * -This is based on how Shopware handles sw-context-token in store-api.
     * @see CallbackController
     */
    public function validateSalesChannelId(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID) ||
            !$this->isRequestScoped($request, StorefrontRouteScope::class)
        ) {
            /**
             * Bail as we aren't targeting storefront.
             */
            return;
        }

        $route = $request->attributes->get('_route');

        if ($route === 'altapay.gateway.redirect') {
            return;
        }

        $controller = $event->getController();
        if (is_array($controller)) {
            $controller = $controller[0] ?? null;
        }

        if (!($controller instanceof CallbackController || $controller === CallbackController::class)) {
            return;
        }

        $orderNumber = $request->get('shop_orderid');
        if (!$orderNumber) {
          $xmlContent = $request->get('xml');

          if (empty($xmlContent) || !is_string($xmlContent)) {
            return;
          }

          try {
            $result = new SimpleXMLElement($xmlContent);
            $orderNumber = (string)($result->Body?->Transactions?->Transaction?->ShopOrderId ?? '');
          } catch (Exception) {
            return;
          }
        }

        if (!$orderNumber) {
            return;
        }


        try {
            $salesChannelId = $this->connection->createQueryBuilder()
                ->select('LOWER(HEX(sales_channel_id))')
                ->from('`order`')
                ->where('`order`.`order_number` = :orderNumber')
                ->andWhere('`order`.`version_id` = :versionId')
                ->setParameter('orderNumber', $orderNumber)
                ->setParameter('versionId', Uuid::fromHexToBytes(Defaults::LIVE_VERSION))
                ->executeQuery()
                ->fetchOne();
        } catch (Exception) {
            return;
        }

        if ($salesChannelId && Uuid::isValid($salesChannelId)) {
            /**
             * Set salesChannelId, as we might be using frontends and this is a storefront callback
             * -meaning that the context/salesChannel is incorrect in the flow.
             */
            $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
        }
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }
}
