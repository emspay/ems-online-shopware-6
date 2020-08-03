<?php declare(strict_types=1);

namespace Ginger\EmsPay\Exception;

class EmsPluginException extends \RuntimeException implements EmsExceptionInterface
{
    public function __construct(string $errorMessage)
    {
        parent::__construct($errorMessage);
    }
}
