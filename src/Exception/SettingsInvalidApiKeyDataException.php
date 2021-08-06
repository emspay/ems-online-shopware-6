<?php declare(strict_types=1);

namespace GingerPlugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SettingsInvalidApiKeyDataException extends ShopwareHttpException implements CustomExceptionInterface
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
        return 'GINGER_PAYMENTS_INVALID_API_CREDENTIALS';
    }
}