# Blesta EPay（易支付） Payment Gateway

A [EPay（易支付）](https://pay.cccyun.cc/) non-merchant gateway plugin for Blesta.

## Install the Gateway
1. Recommended: you can download the plugin via git. 

    Go to /components/gateways/nonmerchant folder and run
    ```
    git clone https://github.com/anshi233/blesta-gateway-epay.git epay
    ```

2. OR You can install the gateway via composer:

    ```
    composer require blesta/epay
    ```

3. OR upload the source code to a /components/gateways/nonmerchant/epay/ directory within
your Blesta installation path.

    For example:

    ```
    /var/www/html/blesta/components/gateways/nonmerchant/epay/
    ```

3. Log in to your admin Blesta account and navigate to
   > Settings > Payment Gateways

4. Find the EPay gateway and click the "Install" button to install it

5. You're done!

# Limitation
* EPay only support CNY. Please use Blesta's currency setting to convert currencies.
* Each payment trascation only support one inovice order.
* Currently no refund support.
* No void invoice support (EPay API does not support it).

# TO-DO
* Any bug fix.
* add refund support. 

# Support me
Considering buying a VPS from [CatServer.ca](https://www.catserver.ca)

# Reference
* [blesta-stripe-universal](https://github.com/anshi233/blesta-stripe-universal)
* [gateway-paypal_checkout](https://github.com/blesta/gateway-paypal_checkout)