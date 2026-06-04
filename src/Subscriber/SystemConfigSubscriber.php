<?php declare(strict_types=1);

namespace Wexo\AltaPay\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wexo\AltaPay\Exception\AltaPayConfigException;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    public const GATEWAY_URL_KEY = 'WexoAltaPay.config.gatewayUrl';

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigChangedEvent::class => 'onBeforeSystemConfigChanged',
        ];
    }

    public function onBeforeSystemConfigChanged(BeforeSystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== self::GATEWAY_URL_KEY) {
            return;
        }

        $value = $event->getValue();

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            return;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return;
        }

        if (!str_starts_with(strtolower($trimmed), 'https://')) {
            $this->logger->error('Rejected AltaPay gateway URL because it is not HTTPS.', [
                'gatewayUrl' => $trimmed,
                'salesChannelId' => $event->getSalesChannelId(),
            ]);

            throw AltaPayConfigException::gatewayUrlNotHttps();
        }
    }
}
