<?php declare(strict_types = 1);

namespace Ginger\EmsPay\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Ginger\ApiClient;
use Ginger\EmsPay\Service\ClientBuilder;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

/**
 * @RouteScope(scopes={"storefront"})
 */

class Webhook extends StorefrontController
{
    /**
     * @var ApiClient
     */

    private $ginger;


    /**
     * @var OrderTransactionStateHandler
     */

    private $transactionStateHandler;

    /**
     * Webhook constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param ClientBuilder $clientBuilder
     */

    public function __construct(OrderTransactionStateHandler $transactionStateHandler, ClientBuilder $clientBuilder)
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->ginger = $clientBuilder->getClient();
    }

    /**
     * @Route("/EmsPay/Webhook", methods={"GET"})
     */

    public function webhook(Request $request, Context $context)
    {
        $request_content = json_decode($request->getContent());

            if (!$request_content->event == 'status_changed') {
                exit;
            }

            $ginger_order = $this->ginger->getOrder($request_content->order_id);
            $shopware_order_id = $ginger_order['extra']['sw_order_id'];
            try{
                $this->transactionStateHandler->reopen($shopware_order_id, $context);
            } catch(\Exception $exception) {
                print('Already opened');
            }

        switch ($ginger_order['status']) {
                case 'accepted':
                case 'completed' : $this->transactionStateHandler->paid($shopware_order_id, $context); break;
                case 'cancelled' : $this->transactionStateHandler->cancel($shopware_order_id, $context); break;
                case 'error' : $this->transactionStateHandler->fail($shopware_order_id, $context); break;
                case 'processing' : $this->transactionStateHandler->process($shopware_order_id, $context); break;
            }
        return new Response('Webhook successful processed',200,[]);
    }
}
