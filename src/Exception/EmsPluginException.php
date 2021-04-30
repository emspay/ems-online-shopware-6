<?php declare(strict_types=1);

namespace GingerPlugin\emspay\Exception;

class EmsPluginException extends \RuntimeException implements EmsStorefrontExceptionInterface
{
    public function __construct(string $errorMessage)
    {
        parent::__construct($errorMessage);
    }
}
