<?php

namespace GingerPlugin\Service;

use Ginger\Ginger;
use GingerPlugin\Components\BankConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class OrderBuilder extends ClientBuilder
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
     * Ginger ShopWare 6 plugin version
     */
    const PLUGIN_VERSION = "1.4.0";

    /**
     * Store the current payment method name;
     */
    public $payment_method;

    /**
     * Store the context in which order builder is called;
     * @var SalesChannelContext
     */
    public $sales_channel_context;

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
     * @return string
     */

    public function getOrderDescription($number)
    {
        $message = 'Your order %s at %s';
        return sprintf($message, (string)$number, $this->sales_channel_context->getSalesChannel()->getName());
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
     * @return mixed|string
     */

    public function getBirthDate($customer)
    {
        if (isset($customer->getBirthday()->date)) {
            return explode(' ', $customer->getBirthday()->date)[0];
        } else if ($this->getPaymentName() == 'afterpay') {
            return str_replace('/', '-', filter_var($_POST['ginger_afterpay'], FILTER_SANITIZE_STRING));
        } else {
            return null;
        }
    }

    /**
     * Function creating the customer array for the Ginger Order
     *
     * @param $info_sales_channel
     * @return array
     *
     */
    public function getCustomer($customer)
    {
        return array_filter([
            'address_type' => 'customer',
            'gender' => $this->getGender($customer->getSalutation()->getSalutationKey()),
            'birthdate' => $this->getBirthDate($customer),
            'country' => $this->getCountryFromAddress($customer->getActiveShippingAddress()),
            'email_address' => $customer->getEmail(),
            'first_name' => $customer->getFirstName(),
            'last_name' => $customer->getLastName(),
            'merchant_customer_id' => (string)$customer->getCustomerNumber(),
            'phone_numbers' => $this->getPhoneNumber($customer),
            'address' => $this->getAddress($customer->getActiveShippingAddress()),
            'locale' => $customer->getLanguage() == "" ? 'en_GB' : $customer->getLanguage()->name,
            'ip_address' => $customer->getRemoteAddress(),
            'additional_addresses' => $this->getAdditionalAddress($customer->getActiveBillingAddress()),
            'postal_code' => $customer->getActiveShippingAddress()->getZipcode(),
        ]);
    }

    /**
     * Retrieving phone number based on Form or Store data.
     * Form data has priority in this case because customer can forgot that he fill the phone number in store account.
     * @param $customer ;
     */
    public function getPhoneNumber($customer)
    {
        if ($this->getPaymentName() == 'afterpay' && $_POST['ginger_afterpay_databagfor_phone_number_place']) {
            return array(filter_var($_POST['ginger_afterpay_databagfor_phone_number_place'], FILTER_SANITIZE_STRING));

        } else {
            return array_filter([$customer->getActiveShippingAddress()->getPhoneNumber()]);
        }
    }

    /**
     * Get country from the customer address array
     *
     * @param $address
     * @return mixed
     */
    protected function getCountryFromAddress($address)
    {
        return $address->getCountry()->getIso();
    }

    /**
     * Get the additional address for the order based on customer billing address
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
     * Get shipping address from customer information.
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
     * @return mixed|null
     */
    public function getIssuerId()
    {
        if (array_key_exists('issuer_id', $this->sales_channel_context->getPaymentMethod()->getCustomFields()) &&
            $this->sales_channel_context->getPaymentMethod()->getCustomFields()['issuer_id']) {
            return $this->sales_channel_context->getPaymentMethod()->getCustomFields()['issuer_id'];
        } else if (isset($_POST['ginger_issuer_id'])) {
            return filter_var($_POST['ginger_issuer_id'], FILTER_SANITIZE_STRING);
        } else {
            return null;
        }
    }

    /**
     * @return mixed
     */

    public function translatePaymentMethod()
    {
        return !is_null($this->getPaymentName()) ? BankConfig::SHOPWARE_TO_BANK_PAYMENTS[$this->getPaymentName()] : null;
    }

    /**
     * Get transactions array which includes required API information
     *
     * @param $issuer
     * @return array
     */

    public function getTransactions( $issuer)
    {
        return array_filter([
            array_filter([
                'payment_method' => $this->translatePaymentMethod(),
                'payment_method_details' => array_filter(['issuer_id' => $this->getPaymentName() == 'ideal' ? $issuer : null])
            ])
        ]);
    }

    /**
     * Forming a Webhook url for Ginger Order based on Shopware host and Webhook controller directory
     *
     * @return string
     */

    public function getWebhookUrl(): string
    {
        return implode('/', [$_SERVER['HTTP_ORIGIN'], 'ginger', 'webhook']);
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

    protected function getProductLines($product, $currency)
    {
        return [
            'name' => $product->getLabel(),
            'amount' => $this->getAmountInCents($product->getTotalPrice()),
            'quantity' => (int)$product->getQuantity(),
            'vat_percentage' => (int)$this->getAmountInCents($this->calculateTax($product->getPrice()->getCalculatedTaxes()->getElements())),
            'merchant_order_line_id' => (string)$product->getProductId(),
            'type' => 'physical',
            'currency' => $currency,
        ];
    }

    /**
     * Function what collect array for shipping order line
     *
     * @param $sales
     * @param $order
     * @return array
     */

    protected function getShippingLines($sales, $order,$currency)
    {
        return [
            'name' => (string)$sales->getShippingMethod()->getName(),
            'amount' => $this->getAmountInCents($order->getShippingCosts()->getTotalPrice()),
            'quantity' => $order->getShippingCosts()->getQuantity(),
            'vat_percentage' => (int)$this->getAmountInCents($this->calculateTax($order->getShippingCosts()->getCalculatedTaxes()->getElements())),
            'merchant_order_line_id' => (string)$sales->getShippingMethod()->getId(),
            'type' => 'shipping_fee',
            'currency' => $currency,
        ];
    }

    /**
     * Sets the context of action
     * @param $sales_channel_context
     */
    public function setSalesChannelContext($sales_channel_context)
    {
        $this->sales_channel_context = $sales_channel_context;
    }

    /**
     * Sets the current payment method name
     */
    public function setPaymentName()
    {
        $payment_name_array = explode('_', $this->sales_channel_context->getPaymentMethod()->getCustomFields()['payment_name']);
        $this->payment_method = end($payment_name_array);
    }

    /**
     * Retrieving current payment method name
     * @return string
     */
    public function getPaymentName()
    {
        return $this->payment_method;
    }

    /**
     * Function that returns order lines in an array for KP Later and Afterpay
     *
     * @param $order
     * @return array|null
     */

    public function getOrderLines($order)
    {
        if (!in_array($this->getPaymentName(), BankConfig::GINGER_REQUIRED_ORDER_LINES_PAYMENTS)) {
            return null;
        }

        $order_lines = [];

        $currency = $order->getCurrency()->getIsoCode();

        foreach ($order->getLineItems()->getElements() as $product) {
            array_push($order_lines, $this->getProductLines($product,$currency));
        }

        if ($order->getShippingCosts()->getUnitPrice() > 0) {
            array_push($order_lines, $this->getShippingLines($this->sales_channel_context, $order, $currency));
        }
        return $order_lines;
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
