<?php


namespace Ginger\EmsPay\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

class paymentKeeper
{
    /**
     * @var array|mixed|null
     */

    private $EmsPayConfig;

    /**
     * @var EntityRepositoryInterface
     */

    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */

    private $salesChanelRepository;

    /**
     * paymentKeeper constructor.
     * @param SystemConfigService $systemConfigService
     * @param EntityRepositoryInterface $paymentMethodRepository
     */

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $paymentMethodRepository
    )
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->EmsPayConfig = $systemConfigService->get('EmsPay.config');
    }

    /**
     * Function which responsible to subscriber to the salesChannel load event
     *
     * @param EntityLoadedEvent $event
     */

    public function onPaymentMethodConfigure(EntityLoadedEvent $event) {
        $salesChannelEntity = current($event->getEntities());
        $payment_methods_ids = $salesChannelEntity->getPaymentMethodIds();

        if (empty($payment_methods_ids)) {
            return;
        }

        foreach($payment_methods_ids as $key => $value){
            $payment_method = $this->findPaymentMethodRepository($value,$event->getContext());
            $this->updatePaymentMethodRepository($payment_method,$event->getContext());
        }

    }

    /**
     * A function that searches for a payment method repository by its id
     *
     * @param string $paymentMethodId
     * @param $context
     * @return mixed|null
     */

    protected function findPaymentMethodRepository(string $paymentMethodId,$context)
    {
        return $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context)->first();
    }

    /**
     * A function that updates the payment method repository
     *
     * @param $paymentMethod
     * @param $context
     * @return EntityWrittenContainerEvent
     */

    protected function updatePaymentMethodRepository($paymentMethod,$context)
    {
        return $this->paymentMethodRepository->update(
            [
                ['id' => $paymentMethod->getId(), 'active' => $this->checkAviability($paymentMethod->getDescription())],
            ],
            $context
        );
    }

    /**
     * A function that matches the user's IP address with the address specified in the plugin settings
     *
     * @param $payment
     * @return bool
     */

    protected function checkAviability($payment) {
        $ginger_payment_label = explode('_',$payment);
        if ($ginger_payment_label[0] == 'emspay' && in_array($ginger_payment_label[1], ['klarnapaylater', 'afterpay'])){
            switch ($ginger_payment_label[1]) {
                case 'afterpay' :
                    $test_ip = $this->EmsPayConfig['emsOnlineAfterpayTestIP'];
                    break;
                case 'klarnapaylater' :
                    $test_ip = $this->EmsPayConfig['emsOnlineKlarnaPayLaterTestIP'];
                    break;
            }
            $request = Request::createFromGlobals();
            $ip = $request->getClientIp();
            return isset($test_ip) ? $ip == $test_ip : true;
        }
        return true;
    }
}
