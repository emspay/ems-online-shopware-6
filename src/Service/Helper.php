<?php

namespace GingerPlugin\emspay\Service;

use Shopware\Core\Framework\Log\LoggerFactory;
use Monolog\Processor\WebProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class Helper
{
    const SHOPWARE_STATES_TO_GINGER =
        [
            'paid' => 'completed',
            'open' => 'new',
            'cancelled' => 'cancelled',
            'processing' => 'processing',
            'in_progress' => 'processing',
            'pending' => 'pending',
            'failed' => 'error'
        ];

    /**
     *  Translator Shopware 6 Payment Description into payment names for Ginger API
     */
    const SHOPWARE_TO_EMS_PAYMENTS =
        [
            'applepay' => 'apple-pay',
            'klarnapaylater' => 'klarna-pay-later',
            'klarnapaynow' => 'klarna-pay-now',
            'paynow' => null,
            'ideal' => 'ideal',
            'afterpay' => 'afterpay',
            'amex' => 'amex',
            'bancontact' => 'bancontact',
            'banktransfer' => 'bank-transfer',
            'creditcard' => 'credit-card',
            'payconiq' => 'payconiq',
            'paypal' => 'paypal',
            'tikkiepaymentrequest' => 'tikkie-payment-request',
            'wechat' => 'wechat',
        ];

    /**
     * EMS Online ShopWare plugin version
     */
    const PLUGIN_VERSION = '1.0.0';

    /**
     * Default currency for Ginger Orders
     */
    const DEFAULT_CURRENCY = 'EUR';

    /**
     * Constructor of the class which includes ginger-php autoload
     */

    /**
     *  Logger Factory
     */
    private $loggerFactory;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * Get amount of the order in cents
     *
     * @param $order_amount
     * @return int
     */

    public function getAmountInCents($order_amount)
    {
        return (int)round((float)$order_amount * 100);
    }

    /**
     * Get order description for Payment Provider Gateways
     *
     * @param $number
     * @param $sales_channel
     * @return string
     */

    public function getOrderDescription($number, $sales_channel)
    {
        $message = 'Your order %s at %s';
        return sprintf($message, (string)$number, $sales_channel->getName());
    }

    /**
     * Function what return a male or female gender for customer array based on Shopware 6 salutation
     *
     * @param $salutation
     * @return string
     */

    protected function getGender($salutation)
    {
        if ($salutation != "")
            return $salutation == 'mr' ? 'male' : 'female';
        else
            return '';
    }

    /**
     * Get birth date from Shopware 6 long date format
     *
     * @param $sales
     * @return mixed|string
     */

    public function getBirthDate($sales)
    {
        return explode(' ', $sales->getBirthday()->date)[0];
    }

    /**
     * Function creating the customer array for the Ginger Order
     *
     * @param $info_sales_channel
     * @return array
     *
     */
    public function getCustomer($info_sales_channel)
    {
        return array_filter([
            'address_type' => 'customer',
            'gender' => $this->getGender($info_sales_channel->getSalutation()->getSalutationKey()),
            'birthdate' => isset($info_sales_channel->getBirthday()->date) ? self::getBirthDate($info_sales_channel) : null,
            'country' => $this->getCountryFromAddress($info_sales_channel->getActiveShippingAddress()),
            'email_address' => $info_sales_channel->getEmail(),
            'first_name' => $info_sales_channel->getFirstName(),
            'last_name' => $info_sales_channel->getLastName(),
            'merchant_customer_id' => (string)$info_sales_channel->getCustomerNumber(),
            'phone_numbers' => array_filter([$info_sales_channel->getActiveShippingAddress()->getPhoneNumber()]),
            'address' => $this->getAddress($info_sales_channel->getActiveShippingAddress()),
            'locale' => $info_sales_channel->getLanguage() == "" ? 'en_GB' : $info_sales_channel->getLanguage(),
            'ip_address' => $info_sales_channel->getRemoteAddress(),
            'additional_addresses' => $this->getAdditionalAddress($info_sales_channel->getActiveBillingAddress()),
            'postal_code' => $info_sales_channel->getActiveShippingAddress()->getZipcode(),
        ]);
    }

    /**
     * Get country from the address array
     *
     * @param $address
     * @return mixed
     */

    protected function getCountryFromAddress($address)
    {
        return $address->getCountry()->getIso();
    }

    /**
     * Get the additional address for the order based on billing address
     *
     * @param $address
     * @return array
     */

    protected function getAdditionalAddress($address)
    {
        return [
            array_filter([
                'address_type' => 'billing',
                'address' => $this->getAddress($address),
                'country' => $this->getCountryFromAddress($address)
            ])
        ];
    }

    /**
     * Get shipping address as default
     *
     * @param $address
     * @return string
     */

    protected function getAddress($address)
    {
        return implode(",", array_filter(array(
            trim($address->getAdditionalAddressLine1()),
            trim($address->getAdditionalAddressLine2()),
            trim($address->getStreet()),
            trim($address->getZipcode()),
            trim($address->getCity()),
        )));
    }

    /**
     * @param $payment
     * @return mixed
     */

    private function translatePaymentMethod($payment)
    {
        return !is_null($payment) ? self::SHOPWARE_TO_EMS_PAYMENTS[explode('emspay_', $payment)[1]] : null;
    }

    /**
     * Get transactions array which includes required API information
     *
     * @param $payment
     * @param $issuer
     * @return array
     */

    public function getTransactions($payment, $issuer)
    {
        $ginger_payment = $this->translatePaymentMethod($payment->getCustomFields()['payment_name']);

        return array_filter([
            array_filter([
                'payment_method' => $ginger_payment,
                'payment_method_details' => array_filter(['issuer_id' => $ginger_payment == 'ideal' ? $issuer : null])
            ])
        ]);
    }

    /**
     * Forming a Webhook url for Ginger Order based on Shopware host and Webhook controller directory
     *
     * @param $amount
     * @return string
     */

    public function getWebhookUrl($amount): string
    {
        return implode('', [$_SERVER['HTTP_ORIGIN']]);
    }

    /**
     * Function creating the extra array as additional array for a Ginger order
     * includes a Shopware order id and Plugin version
     *
     * @param $id
     * @return array
     */

    public function getExtraArray($id)
    {
        return ['plugin' => sprintf('ShopWare 6 v%s', self::PLUGIN_VERSION),
            'sw_order_id' => $id];
    }

    /**
     * A function that calculates the total tax for positions in the order lines array
     *
     * @param $taxElements
     * @return int
     */

    protected function calculateTax($taxElements)
    {
        $summ = 0;
        foreach ($taxElements as $tax) {
            $summ += $tax->getTax();
        }
        return $summ;
    }

    /**
     * Function what collect array for product order line
     *
     * @param $product
     * @return array
     */

    protected function getProductLines($product)
    {
        return [
            'name' => $product->getLabel(),
            'amount' => self::getAmountInCents($product->getTotalPrice()),
            'quantity' => (int)$product->getQuantity(),
            'vat_percentage' => (int)self::getAmountInCents(self::calculateTax($product->getPrice()->getCalculatedTaxes()->getElements())),
            'merchant_order_line_id' => (string)$product->getProductId(),
            'type' => 'physical',
            'currency' => self::DEFAULT_CURRENCY,
        ];
    }

    /**
     * Function what collect array for shipping order line
     *
     * @param $sales
     * @param $order
     * @return array
     */

    protected function getShippingLines($sales, $order)
    {
        return [
            'name' => (string)$sales->getShippingMethod()->getName(),
            'amount' => self::getAmountInCents($order->getShippingCosts()->getTotalPrice()),
            'quantity' => $order->getShippingCosts()->getQuantity(),
            'vat_percentage' => (int)self::getAmountInCents(self::calculateTax($order->getShippingCosts()->getCalculatedTaxes()->getElements())),
            'merchant_order_line_id' => (string)$sales->getShippingMethod()->getId(),
            'type' => 'shipping_fee',
            'currency' => 'EUR',
        ];
    }

    /**
     * Function that returns order lines in an array for KP Later and Afterpay
     *
     * @param $sales
     * @param $order
     * @return array|null
     */

    public function getOrderLines($sales, $order)
    {
        if (!in_array($sales->getPaymentMethod()->getCustomFields()['payment_name'], ['emspay_klarnapaylater', 'emspay_afterpay'])) {
            return null;
        }
        $order_lines = [];
        foreach ($order->getLineItems()->getElements() as $product) {
            array_push($order_lines, self::getProductLines($product));
        }
        $order->getShippingCosts()->getUnitPrice() > 0 ? array_push($order_lines, self::getShippingLines($sales, $order)) : null;
        return $order_lines;
    }

    /**
     * Function saveEMSLog
     * Writes a log to a file /app/var/log/ems_plugin_%environment%-%current date%. If there are more than 7 logging files in the log directory, removes the oldest
     *
     * @param $msg
     * @param $context
     */
    public function saveEMSLog($msg, $context)
    {
        $ems_logger = $this->loggerFactory->createRotating('ems_plugin', 7);
        $ems_logger->pushProcessor(new WebProcessor());
        $ems_logger->error($msg, $context);
    }

    /**
     * Save the Additional Order information into Shopware Order for keep some links between Ginger API and Shopware 6
     *
     * @param $orderTransactionId
     * @param $content
     * @param $orderRepository
     * @param $context
     * @return mixed
     */

    public function saveGingerInformation($orderTransactionId, $content, $orderRepository, $context)
    {
        //Search the Shopware Order using transaction id.
        $order = $this->searchShopwareOrder($orderRepository, $orderTransactionId, $context);

        //Update customFields.
        $order_custom_fields = $order->getCustomFields();
        $order_custom_fields = array_merge(
            empty($order_custom_fields) ? [] : $order_custom_fields,
            $content
        );

        //Return updated Shopware order.
        return $this->updateShopwareOrderRepository($orderRepository, $order_custom_fields, $order, $context);
    }

    /**
     * Searching Shopware Order Repository Entity using transaction id
     *
     * @param $orderRepository
     * @param $orderTransactionId
     * @param $context
     * @return mixed
     */
    public function searchShopwareOrder($orderRepository, $orderTransactionId, $context)
    {
        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new EqualsFilter('transactions.id', $orderTransactionId));
        return $orderRepository->search($orderCriteria, $context)->first();
    }

    /**
     * Updating Shopware Order Repository Entity custom fields
     *
     * @param $orderRepository
     * @param $order_custom_fields
     * @param $order
     * @param $context
     * @return mixed
     */
    public function updateShopwareOrderRepository($orderRepository, $order_custom_fields, $order, $context)
    {
        return $orderRepository->update(
            [
                ['id' => $order->getId(), 'customFields' => $order_custom_fields],
            ],
            $context
        );
    }
}
