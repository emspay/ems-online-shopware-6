<?php declare(strict_types=1);

namespace Ginger\EmsPay\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Order\OrderEvents;

class captureOrder implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            OrderEvents::ORDER_DELIVERY_WRITTEN_EVENT => 'DeliveryTime'
        ];
    }

    public function DeliveryTime(EntityLoadedEvent $event): void
    {
        print_r("There is still nothing");
    }
}
