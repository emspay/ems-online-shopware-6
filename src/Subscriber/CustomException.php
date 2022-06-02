<?php

namespace GingerPlugin\Subscriber;

use Dompdf\Exception;
use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\GingerCustomerNotifierTrait;
use GingerPlugin\Exception\CustomStorefrontExceptionInterface;
use Monolog\Processor\WebProcessor;
use Shopware\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopware\Core\Framework\Log\LoggerFactory;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Controller\ErrorController;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CustomException implements EventSubscriberInterface
{
    use GingerCustomerNotifierTrait;

    private $loggerFactory;
    private $errorController;
    private $requestStack;

    /**
     * Custom Ginger Exceptions constructor.
     * @param ErrorController $errorController
     * @param RequestStack $requestStack
     * @param LoggerFactory $loggerFactory
     */

    public function __construct(ErrorController $errorController, RequestStack $requestStack, LoggerFactory $loggerFactory)
    {
        $this->errorController = $errorController;
        $this->requestStack = $requestStack;
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            KernelEvents::EXCEPTION => [
                ['processException', -1],
            ]
        ];
    }

    public function processException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($event->getThrowable() instanceof CustomStorefrontExceptionInterface) {
            $this->saveToBacklog($exception->getMessage(), ['FILE' => $exception->getFile(), 'FUNCTION' => $exception->getTrace()[0]['function'], 'LINE' => $exception->getLine()]);
            $this->showWarning($event, $exception->getMessage());
            //$event->setResponse((new ErrorResponseFactory())->getResponseFromException($exception, true));
        }

        if ($event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return $event;
        }

        if (!$event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            return $event;
        }
//        $response = $this->errorController->error(
//            $event->getThrowable(),
//            $this->requestStack->getMasterRequest(),
//            $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)
//        );

        $this->saveToBacklog($exception->getMessage(), ['FILE' => $exception->getFile(), 'FUNCTION' => $exception->getTrace()[0]['function'], 'LINE' => $exception->getLine()]);

        $this->showWarning($event, $exception->getMessage());

        //      $event->setResponse((new ErrorResponseFactory())->getResponseFromException($exception, false));

        return $event;
    }



    /**
     * Function save to log
     * Writes a log to a file /app/var/log/<FILE_PREFIX from BANK_CONFIG>%environment%-%current date%. If there are more than 7 logging files in the log directory, removes the oldest
     *
     * @param $msg
     * @param $context
     */
    public function saveToBacklog($msg, $context)
    {
        try {
            $logger = $this->loggerFactory->createRotating(BankConfig::FILE_PREFIX, 7);
            $logger->pushProcessor(new WebProcessor());
            $logger->error($msg, $context);
        } catch (Exception $exception) {
        }
    }
}
