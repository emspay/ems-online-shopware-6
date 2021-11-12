# Changelog Shopware 6

## 1.0.0 

* Initial Version

## 1.1.0

* Has been implemented: 
    * Order Capturing functionality.
    * Webhook settings option.
    * Test API-Key options in settings for : 
        * Klarna Pay Later.
        * Afterpay.
    * Test IP Address options in settings for : 
        * Afterpay.
        * Klarna Pay Later.
    * Country Validation for Afterpay.
    * Error Logging & Error Processing.
    * Issuer Id Selector field.
    * Afterpay birthday select field.

## 1.2.0

* Added iBAN information for Bank-Transfer payment method.
* Fixed AfterPay Country Validation. 
* Removed use webhook option from settings (also from code realization).

## 1.2.1

* Fixed iDealIssuer issue related to last API update.
  
## 1.3.0

* Updates & fixes based on Shopware 6 guidelines.
* Added Test API Key button to settings page.
* Added Afterpay birthday validation.

## 1.4.0 

* Refactored code to handle GPE solution. 
* Unified bank labels to handle GPE solution.
* Applied improvements from WooCommerce support.
* Updated methods to provide custom form for Afterpay birthday field after Shopware version update.
* Fixed bug with empty API settings field. 
* Fixed Select Ideal Issuer form on checkout page and in backend after Shopware version update.
* Fixed Webhook functionality, when in response retrieved message that the route is missing after Shopware version update.
* Fixed Test API Key button on plugin settings page after Shopware version update.
* Fixed Payment Keeper for Afterpay Country Validation and IP Filtering.
* Fixed issue when exception logged twice, when only once expected.
* Fixed SalesChannelSwitch errors, which occurs outside the checkout page.
* Fixed CaptureOrder Subscriber wrong event calls.
* Added check to CaptureOrder Subscriber to handle calls only from ginger plugins.
* Added new Gateways for handling custom bank functionality requests.
* Added keeping payments in case when invalid API Key entered.
* Added Afterpay phone number custom field on checkout page. 
* Added supporting for Edit Order page, which loaded when customer try to complete the cancelled order.
* Replaced ‘reason’ by ‘customer_message’.
* Implemented AfterMergeTest using PHPUnit extension to check GPE solution on step GitHub actions.
* Implemented CreateOrderTest using PHPUnit extension to check that latest changes doesn't crash the main functionality.
* Implemented AsyncPaymentFinalizeException in case when order after pay attempt setts with status ‘error’.
* Implemented GingerFeaturesCheck and GingerPaymentMethodRepositoryWorker traits to provide reusing code for checking availability in other places in the plugin.

## 1.5.0

* Order extra array expanded to provide more detailed information.
* Implemented subscriber ReturnUrlExceptions to provide warning messages to customer with information from ginger order.
* Implemented hide custom form method if the checkout page is actually edit order page. 
* Implemented `orderLines` for every order.
* Implemented test coverage for expanded order extra array.
* Implemented improvement into PHPUnit tests coverage.
* Implemented Refund feature.
* Implemented check for selecting Bank Issuer option for iDeal.
* Implemented custom styles for ginger inputs.
* Templates for payment methods on checkout have been decomposed.
* Fixes `vatPercentage` orderLines mismatch.
* Fixes `orderLines` mismatch when quantity of product > 1.