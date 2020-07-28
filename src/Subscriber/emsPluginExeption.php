<?php

namespace Ginger\EmsPay\Subscriber;

use Ginger\ApiClient;
use Ginger\EmsPay\Service\Helper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Ginger\EmsPay\Exception\EmsExceptionInterface;

class emsPluginExeption implements EventSubscriberInterface
{

    /**
     * @var EntityRepositoryInterface
     */

    private $paymentMethodRepository;

    /**
     * @var Helper
     */

    private $helper;

    /**
     * @var ApiClient
     */

    private $ginger;

    private $errorController;
    private $requestStack;

    /**
     * emsPluginExeption constructor.
     * @param SystemConfigService $systemConfigService
     * @param Helper $helper
     */
    public function __construct(SystemConfigService $systemConfigService, Helper $helper, ErrorController $errorController, RequestStack $requestStack)
    {
        $this->helper = $helper;
        $EmsPayConfig = $systemConfigService->get('EmsPay.config');
        $this->ginger = $this->helper->getGignerClinet($EmsPayConfig['emsOnlineApikey'], $EmsPayConfig['emsOnlineBundleCacert']);
        $this->errorController = $errorController;
        $this->requestStack = $requestStack;
    }

    /**
     * @return array|string[]
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            KernelEvents::EXCEPTION => [
                ['logException', 10],
                ['processException', 10],
            ]
        ];
    }

    /**
     * @param ExceptionEvent $event
     */
    public function processException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof EmsExceptionInterface) {
            return;
        }

        $response = $this->errorController->error(
            $event->getThrowable(),
            $this->requestStack->getMasterRequest(),
            $event->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)
        );

        $event->setResponse($response);
    }

    /**
     * @param ExceptionEvent $event
     */
    public function logException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof EmsExceptionInterface) {
            return;
        }

        $this->helper->saveEMSLog($event->getThrowable()->getMessage(), ['FILE' => $event->getThrowable()->getFile(), 'FUNCTION' => $event->getThrowable()->getTrace()[0]['function'], 'LINE' => $event->getThrowable()->getLine()]);
    }
}
