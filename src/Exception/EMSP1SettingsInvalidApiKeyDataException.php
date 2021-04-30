<?php declare(strict_types=1);

namespace GingerPlugin\emspay\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class emspaySettingsInvalidApiKeyDataException extends ShopwareHttpException implements EmsExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Provided API credentials are invalid');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }

    public function getErrorCode(): string
    {
        return 'emspay_PAYMENTS_INVALID_API_CREDENTIALS';
    }
}