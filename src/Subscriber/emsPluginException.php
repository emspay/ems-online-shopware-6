<?php

namespace GingerPlugin\emspay\Subscriber;

use Ginger\ApiClient;
use GingerPlugin\emspay\Exception\EmsStorefrontExceptionInterface;
use GingerPlugin\emspay\Service\ClientBuilder;
use GingerPlugin\emspay\Service\Helper;
use GingerPlugin\emspay\Exception\EmsExceptionInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class emsPluginException implements EventSubscriberInterface
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
     * emsPluginException constructor.
     * @param ClientBuilder $clientBuilder
     * @param Helper $helper
     * @param ErrorController $errorController
     * @param RequestStack $requestStack
     */
    public function __construct(ClientBuilder $clientBuilder, Helper $helper, ErrorController $errorController, RequestStack $requestStack)
    {
        $this->helper = $helper;
        $this->ginger = $clientBuilder->getClient();
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
        if (!$event->getThrowable() instanceof EmsStorefrontExceptionInterface) {
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
