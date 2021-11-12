<?php

namespace GingerPlugin\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;

class ReturnUrlExceptions implements EventSubscriberInterface
{
    public $client;

    /**
     * ReturnUrlExceptions constructor.
     * @param Redefiner $redefiner
     */
    public function __construct(Redefiner $redefiner)
    {
        $this->client = $redefiner->getClient();
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            AccountEditOrderPageLoadedEvent::class => 'processCustomMessage',
        ];
    }

    /**
     * @param AccountEditOrderPageLoadedEvent $event
     */
    public function processCustomMessage(AccountEditOrderPageLoadedEvent $event)
    {
        try {
            $type = 'warning';
            $session = $event->getRequest()->getSession();
            $flash_bag = $session->getFlashBag();
            $flash_bag->add($type, $this->determinateMessage(
                $event->getPage()->getOrder()->getCustomFields()
            ));
        } catch (\Exception $exception) {
            throw new CustomPluginException($exception->getMessage(), 500, 'GINGER_UPDATE_CUSTOM_FIELDS_ERROR_ERROR');
        }
    }

    public function determinateMessage($order_custom_fields): string
    {
        if (!array_key_exists('ginger_order_id', $order_custom_fields)) {
            return 'Sorry, at this time payment method cannot process entered data.';
        }

        $gingerOrder = $this->client->getOrder($order_custom_fields['ginger_order_id']);
        if ($gingerOrder['status'] == 'error') {
            return current($gingerOrder['transactions'])['customer_message'];
        }
        if ($gingerOrder['status'] == 'new') {
            return 'The payment was canceled on the payment provider page';
        }
    }
}
