<?php

namespace GingerPlugin\Components;

class GingerConfig
{
    const SHOPWARE_VERSION = '6.4.2.1';

    const SHOPWARE_TO_BANK_PAYMENTS =
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
            'googlepay' => 'google-pay',
            'klarnadirectdebit' => 'klarna-direct-debit',
            'sofort' => 'sofort',
            'viacash' => 'viacash'
        ];

    const PLUGIN_SETTINGS = [
        'GingerAfterPayCountries',
        'GingerAfterpayTestIP',
        'GingerAPIKey',
        'GingerBundleCacert',
        'GingerKlarnaPayLaterTestIP',
        'GingerKlarnaTestAPIKey',
        'GingerAfterpayTestAPIKey',
    ];

    const GINGER_PAYMENTS_LABELS = [
        'klarnapaylater' => 'Klarna Pay Later',
        'klarnapaynow' => 'Klarna Pay Now',
        'paynow' => 'Pay Now',
        'applepay' => 'Apple Pay',
        'ideal' => 'iDEAL',
        'afterpay' => 'Afterpay',
        'amex' => 'American Express',
        'bancontact' => 'Bancontact',
        'banktransfer' => 'Bank Transfer',
        'creditcard' => 'Credit Card',
        'paypal' => 'PayPal',
        'payconiq' => 'Payconiq',
        'tikkiepaymentrequest' => 'Tikkie Payment Request',
        'sofort' => 'Sofort',
        'klarnadirectdebit' => 'Klarna Direct Debit',
        'googlepay' => 'Google Pay',
        'viacash' => 'Viacash'
    ];

    const GINGER_IP_VALIDATION_PAYMENTS = [
        'afterpay',
        'klarnapaylater',
    ];

    const GINGER_REQUIRED_IBAN_INFO_PAYMENTS = [
        'bank-transfer',
    ];
}
