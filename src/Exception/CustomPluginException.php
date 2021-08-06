<?php declare(strict_types=1);

namespace GingerPlugin\Exception;

use phpDocumentor\Reflection\Types\This;
use Shopware\Core\Framework\ShopwareHttpException;

class CustomPluginException extends ShopwareHttpException implements CustomStorefrontExceptionInterface
{
    public function __construct(string $errorMessage, int $code ,string $errorCode)
    {
        $this->message = $errorMessage;
        $this->code = $code;
        $this->parameters['error_code'] = $errorCode;
        parent::__construct($errorMessage);
    }

    public function getErrorCode(): string
    {
        return $this->parameters['error_code'] ?? '500';
    }

    public function getStatusCode(): int
    {
        return $this->code;
    }
}
