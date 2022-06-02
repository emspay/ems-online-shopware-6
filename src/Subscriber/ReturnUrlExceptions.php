<?php

namespace GingerPlugin\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\Components\GingerExceptionHandlerTrait;
use GingerPlugin\Exception\CustomPluginException;
use GingerPlugin\Components\Redefiner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;

class ReturnUrlExceptions implements EventSubscriberInterface
{
    use GingerExceptionHandlerTrait;

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
            $this->handleException($exception, $event);
        }
    }

    public function determinateMessage($order_custom_fields): string
    {
        if (!array_key_exists('ginger_order_id', $order_custom_fields)) {
            if (array_key_exists('error_message', $order_custom_fields)) {
                return $order_custom_fields['error_message'];
            }
            return 'Sorry, at this time payment method cannot process entered data.';
        }

        $gingerOrder = $this->client->getOrder($order_custom_fields['ginger_order_id']);

        $processing = function ($gingerOrder) {
            $message = 'The payment is processing. ';
            $transaction = current($gingerOrder['transactions']);
            if ($transaction['status'] == 'error') {
                $message .= $transaction['customer_message'] ?? $transaction['reason'];
            }
            return $message;
        };

        switch ($gingerOrder['status']) {
            case 'error':
                return current($gingerOrder['transactions'])['customer_message'];
            case 'new':
                return 'The payment was canceled on the payment provider page';
            case 'expired':
                return 'The payment is expired, for now, it is not possible to reopen the order.';
            case 'processing' :
                return $processing($gingerOrder);
            default :
                return sprintf("The order status is out of scope : %s", $gingerOrder["status"]);
        }
    }
}
