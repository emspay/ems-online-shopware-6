# EMS Online plugin for Shopware 6
This is the offical EMS Online plugin.

## About
By integrating your webshop with EMS Online you can accept payments from your customers in an easy and trusted manner with all relevant payment methods supported.


## Version number
Version 1.0.0


## Pre-requisites to install the plug-ins: 
- PHP v7.2 and above
- MySQL v8 and above

## Installation
Manual installation of the Shopware 6 plugin using (s)FTP

1. Unzip your archive with the plugin. Put the 'EmsPay' folder to the directory <i><b>root</b></i>/custom/plugins/. 
2. Go to the admin panel, this will be '/admin' in your URL address. 
3. Open tab Settings>System>Plugins. 
    * In list front of you search "EMS Online".
    * On the last column click the button with three dots, this will show an additional menu.
    * Click the "Install" Field. 
    * After the installation in the same menu click the "Config" field.  
    * Put you API key in the next opened window and choice CaCert option (by default is activated). 
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
