<?php

namespace GingerPlugin\Components;

use GingerPlugin\Exception\CustomPluginException;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

trait GingerPaymentMethodRepositoryWorkerTrait
{

    /**
     * Finding a payment method ids in Sales Channel context.
     */
    public function getPaymentMethodIds($salesChannelContext)
    {
        return $salesChannelContext->getSalesChannel()->getPaymentMethodIds();
    }

    /**
     * A function that searches for a payment method repository by its id.
     */
    protected function findPaymentMethodById($paymentMethodId, $context)
    {
        return $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context)->first();
    }

    /**
     * Updating activity for currency payment method.
     */
    protected function updatePaymentMethodActiveStatus($paymentMethod, $context, $active)
    {
        try {
            if ($paymentMethod->getActive() == $active) {
                return $paymentMethod;
            }

            // TODO: That can be implementation for task PLUG-766
//            if ($active == false) {
//                $this->errors->add(
//                    new PaymentMethodBlockedError((string) $paymentMethod)
//                );
//            }

            return $this->paymentMethodRepository->update(
                [
                    ['id' => $paymentMethod->getId(), 'active' => $active],
                ],
                $context
            );
        } catch (\Exception $exception) {
            throw new CustomPluginException(
                $exception->getMessage(),
                500,
                'GINGER_FAILED_TO_KEEP_PAYMENT_AFTER_VALIDATION');
        }
    }
}