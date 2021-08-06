<?php declare(strict_types=1);

namespace GingerPlugin\Controller;

use Ginger\ApiClient;
use GingerPlugin\Components\Redefiner;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

# don't remove this 2 uses, important for shopware classes!
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class Webhook extends StorefrontController
{
    private $transactionStateHandler;
    private $ginger;

    /**
     * Webhook constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param Redefiner $redefiner
     */
    public function __construct(OrderTransactionStateHandler $transactionStateHandler, Redefiner $redefiner)
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->ginger = $redefiner->getClient();
    }

    /**
     * @Route("/ginger/webhook", defaults={"csrf_protected"=false}, methods={"POST"})
     */

    public function webhook(Request $request, Context $context): Response
    {
        $request_content = json_decode($request->getContent());
        $response = [];
        if ($request_content->event != 'status_changed') {
            array_push($response, 'Wrong webhook event, check params');
            return new Response(json_encode($response), 400, []);
        }

        $ginger_order = $this->ginger->getOrder($request_content->order_id);
        $shopware_order_id = $ginger_order['extra']['sw_order_id'];
        try {
            $this->transactionStateHandler->reopen($shopware_order_id, $context);
        } catch (\Exception $exception) {
            array_push($response, 'Already opened');
        }
        try {
            switch ($ginger_order['status']) {
                case 'accepted':
                case 'completed' :
                    $this->transactionStateHandler->paid($shopware_order_id, $context);
                    break;
                case 'cancelled' :
                    $this->transactionStateHandler->cancel($shopware_order_id, $context);
                    break;
                case 'error' :
                    $this->transactionStateHandler->fail($shopware_order_id, $context);
                    break;
                case 'processing' :
                    $this->transactionStateHandler->process($shopware_order_id, $context);
                    break;
            }
            array_push($response, 'Webhook successful processed');
            return new Response(json_encode($response), 200, []);
        } catch (\Exception $exception) {
            array_push($response, $exception->getMessage());
            return new Response(json_encode($response), 400, []);
        }
    }
}
