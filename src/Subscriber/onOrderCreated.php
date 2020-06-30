<?php declare(strict_types=1);

namespace Ginger\EmsPay\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Cart\CartEvents;
class onOrderCreated implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            CartEvents::CHECKOUT_ORDER_PLACED => 'onOrderCreated'
        ];
    }

    public function onOrderCreated(EntityLoadedEvent $event): void
    {
        print_r('123123');
        exit;
    }
}
