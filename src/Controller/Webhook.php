<?php declare(strict_types = 1);

namespace Ginger\EmsPay\Controller;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Ginger\EmsPay\Vendor\Helper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
/**
 * @RouteScope(scopes={"storefront"})
 */

class Webhook extends AbstractController
{
    /**
     * @var Helper
     */

    private $helper;

    /**
     * @var \Ginger\ApiClient
     */

    private $ginger;

    /**
     * @var SystemConfigService
     */

    private $EmsPayConfig;

    /**
     * @var OrderTransactionStateHandler
     */

    private $transactionStateHandler;

    /**
     * Webhook constructor.
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param SystemConfigService $systemConfigService
     */

    public function __construct(OrderTransactionStateHandler $transactionStateHandler, SystemConfigService $systemConfigService)
    {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->helper = new Helper();
        $EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->ginger = $this->helper->getGignerClinet($EmsPayConfig['emsOnlineApikey'], $EmsPayConfig['emsOnlineBundleCacert']);
    }

    /**
     * @Route("/EmsPay/Webhook", name="frontend.checkout.emspay.webhook", options={"seo"="false"}, methods={"GET"})
     */

    public function webhook(Request $request, Context $context)
    {
        $request_content = json_decode($request->getContent());

            if (!$request_content->event == 'status_changed') {
                exit;
            }

            $ginger_order = $this->ginger->getOrder($request_content->order_id);
            $shopware_order_id = $ginger_order['extra']['sw_order_id'];

            $this->transactionStateHandler->reopen($shopware_order_id, $context);

        switch ($ginger_order['status']) {
                case 'completed' : $this->transactionStateHandler->paid($shopware_order_id, $context); break;
                case 'cancelled' : $this->transactionStateHandler->cancel($shopware_order_id, $context); break;
                case 'error' : $this->transactionStateHandler->fail($shopware_order_id, $context); break;
                case 'processing' : $this->transactionStateHandler->process($shopware_order_id, $context); break;
            }
    }
}
