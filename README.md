# EMS Online plugin for Shopware 6
This is the offical EMS Online plugin.

## About
By integrating your webshop with EMS Online you can accept payments from your customers in an easy and trusted manner with all relevant payment methods supported.


## Version number
Version 1.1.0


## Pre-requisites to install the plug-ins: 
- PHP v7.2 and above
- MySQL v8 and above

## Installation
Manual installation of the Shopware 6 plugin using (s)FTP

1. Create the 'EmsPay' folder in the directory <i><b>root</b></i>/custom/plugins/. Unzip your archive with the plugin to that folder.
2. Go to the admin panel, this will be '/admin' in your URL address. 
3. Open tab Settings>System>Plugins. 
    * In list front of you search "EMS Online".
    * On the last column click the button with three dots, this will show an additional menu.
    * Click the "Install" Field. 
    * After the installation in the same menu click the "Config" field.  
    * Put you API key in the next opened window.
    * Choice CaCert option (by default is activated). 
    * Choice the use webhook option (by default is activated).
    * Are you offering Klarna on your pay page? In that case enter the following fields:
        * Test API key field. Copy the API Key of your test webshop in the Test API key field. When your Klarna application is approved an extra test webshop was created for you to use in your test with Klarna. The name of this webshop starts with ‘TEST Klarna’.
        * Klarna IP For the payment method Klarna you can choose to offer it only to a limited set of whitelisted IP addresses. You can use this for instance when you are in the testing phase and want to make sure that Klarna is not available yet for your customers. If you do not offer Klarna you can leave the Test API key and Klarna debug IP fields empty.
    * Are you offering Afterpay on your pay page?
        * To allow AfterPay to be used for any other country just add its country code (in ISO 2 standard) to the "Countries available for AfterPay" field. Example: BE, NL, FR.
        * See the instructions for Klarna.
    * Then turn switch what named "Activate", to turn on the plugin.
4. Open tab Settings>Shop>Payment. There you can find payment method what you want to use. By default all payment after installation is disabled. To set payment as active:
    * Choice payment from the list of all payment methods.
    * On the last column click the button with three dots, this will show an additional menu.
    * Click the "Edit" field.
    * On next opened form turn switch "Active" to enable payment.
    * After all changes click "Save" button to save the changes. 
5. Once you installed the plugin - offer the payment methods in your webshop.
    * On the left menu opens your Sales Channel, by default this called "Storefront".
    * On "General" tab, you can find the block "General Settings".
    * On that area find the "Payment" options.
    * Click on the empty space to see the list of EMS Online payments methods, and select the ones you want to add to the Shop.
    * After all, operations click the "Save" button to save changes.
6. Compatibility: Shopware 6.*
