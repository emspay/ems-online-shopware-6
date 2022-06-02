<?php

namespace GingerPlugin\Components;

use Exception;
use GingerPlugin\Components\GingerCustomerNotifierTrait;
use Monolog\Processor\WebProcessor;

trait GingerExceptionHandlerTrait
{
    use GingerCustomerNotifierTrait;

    public $exception;

    /**
     * Handle the exception, log it to file with all required to debug data.
     * @param \Exception $exception The exception which occurs, full object with trace.
     * @param null $event The event what occurs, need to display message for customer.
     */
    public function handleException(\Exception $exception, $event = null)
    {
        $this->exception = $exception;
        $this->saveToBacklog($exception->getMessage(), [
            "near" => $this->getLastTrace($event),
            "trace" => $this->exception->getTrace()
        ]);
        if (!$event) {
            return;
        }
        switch ($event->getSalesChannelContext() !== null) {
            case 'true' :
                $this->showWarning($event, $exception->getMessage());
                break;
            case 'false' :
                break;
        }
    }

    /**
     * Get N-count entities from Exception trace, to debug what's happen and where while processing error.
     *
     * @param int $count
     * @return array
     */
    public function getLastTrace($event): array
    {
        return array_filter([
            'code' => $this->exception->getCode(),
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'request' => $event
        ]);
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
        if (!isset($this->loggerFactory)) {
            return;
        }
        try {
            $logger = $this->loggerFactory->createRotating(BankConfig::FILE_PREFIX, 7);
            $logger->pushProcessor(new WebProcessor());
            $logger->error($msg, $context);
        } catch (Exception $exception) {
            $a = $exception;
        }
    }
}