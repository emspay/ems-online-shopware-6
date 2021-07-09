<?php

namespace GingerPlugin;

use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\GingerConfig;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

class emspay extends Plugin
{
    const GATEWAY_HANDLER = Redefiner::class;

    public function install(InstallContext $context): void
    {
        $this->addPaymentMethod($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method to inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders
        $this->setPaymentMethodIsActive(false, $context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        parent::deactivate($context);
    }

    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment method exists already, no need to continue here
        if ($paymentMethodExists) {
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(\get_class($this), $context);
        /** @var EntityRepositoryInterface $paymentRepository */

        $paymentRepository = $this->container->get('payment_method.repository');

        foreach (GingerConfig::GINGER_PAYMENTS_LABELS as $key => $value) {
            $this->addGignerPayment($key, $value, $paymentRepository, $pluginId, $context);
        }
    }

    private function addGignerPayment($name, $label, $paymentRepository, $pluginId, $context)
    {
        $payment = [
            // payment handler will be selected by the identifier
            'handlerIdentifier' => self::GATEWAY_HANDLER,
            'name' => implode(' - ', [BankConfig::PAYMENT_METHODS_PREFIX, $label]),
            'customFields' => ['payment_name' => implode('_', ['ginger', $name])],
            'description' => 'Pay using ' . $label,
            'translations' => $this->getTranslations($label, implode('_', ['ginger', $name])),
            'pluginId' => $pluginId,
        ];

        $paymentRepository->create([$payment], $context);
    }

    private function getTranslations($payment_name, $payment_code): array
    {
        return [
            'de-DE' => [
                'name' => implode(' - ', [bankConfig::PAYMENT_METHODS_PREFIX, $payment_name]),
                'description' => implode('', ['Bezahlen mit ', $payment_name]),
                'customFields' => ['payment_name' => $payment_code]
            ],
            'en-GB' => [
                'name' => implode(' - ', [bankConfig::PAYMENT_METHODS_PREFIX, $payment_name]),
                'description' => implode('', ['Pay using ', $payment_name]),
                'customFields' => ['payment_name' => $payment_code]
            ],
        ];
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodIds = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodIds) {
            return;
        }

        foreach ($paymentMethodIds as $paymentId) {
            $paymentMethod = [
                'id' => $paymentId,
                'active' => $active,
            ];
            $paymentRepository->update([$paymentMethod], $context);
        }
    }

    private function getPaymentMethodId(): ?array
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', self::GATEWAY_HANDLER));

        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds();
    }
}
