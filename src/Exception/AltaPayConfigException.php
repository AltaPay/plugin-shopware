<?php declare(strict_types=1);

namespace Wexo\AltaPay\Exception;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class AltaPayConfigException extends HttpException
{
    public const GATEWAY_URL_NOT_HTTPS = 'WEXO_ALTAPAY__GATEWAY_URL_NOT_HTTPS';

    public static function gatewayUrlNotHttps(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::GATEWAY_URL_NOT_HTTPS,
            'AltaPay Gateway URL must use HTTPS (https://). Please update the configured URL.'
        );
    }
}
