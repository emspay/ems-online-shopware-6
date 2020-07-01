<?php declare(strict_types=1);

namespace Ginger\EmsPay\Subscriber;

use Ginger\EmsPay\Service\Gateway;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class getConfig implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'onPaymentLoaded'
        ];
    }

    public function onPaymentLoaded(EntityLoadedEvent $event): void
    {
print_r(123);exit;
    }
}

