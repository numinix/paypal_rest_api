# PayPal RESTful API Payment Module
This Zen Cart payment module (`paypalr`) combines the processing for the **PayPal Payments Pro** (`paypaldp`) and **PayPal Express Checkout** (`paypalwpp`) payment modules that are currently built into Zen Cart distributions.  Instead of using the older NVP (**N**ame **V**alue **P**air) methods to communicate with PayPal, this payment module uses PayPal's now-current [REST APIs](https://developer.paypal.com/api/rest/) and combines the two legacy methods into one.

Zen Cart Support Thread: https://www.zen-cart.com/forumdisplay.php?170-PayPal-RESTful-support

Zen Cart Plugin Download Link: https://www.zen-cart.com/downloads.php?do=file&id=2382

**Zen Cart compatibility.** The payment module supports Zen Cart v1.5.7c and later, provided that the following requirements are met:

1. Apply the module's [required `order_total` notifier patch](https://github.com/lat9/paypalr/wiki/Required-changes-to-%60-includes-classes-order_total.php%60); this is the only change that touches a core file.
2. Use the bundled ObserverManager trait shim that this module installs automatically.
3. Use the bundled language shim that this module installs automatically.

Those shims provide the 1.5.8a trait and language fallbacks automatically&mdash;no additional downloads are needed beyond the `order_total` patch.

The module's operation has been validated …

1. With PHP versions 7.4 through 8.4; **PHP 7.3 will result in fatal PHP errors!**
2. In Zen Cart's 3-page checkout environment (v1.5.7**c**+, v2.0.x and v2.1.0)
3. With One-Page Checkout  (OPC), v2.4.6-2.5.3
   1. Using *OPC*'s guest-checkout feature.
   2. Both requiring confirmation and not!
4. With both the built-in responsive_classic and [ZCA Bootstrap](https://www.zen-cart.com/downloads.php?do=file&id=2191) (v3.6.2-3.7.7) templates.

For additional information, refer to the payment-module's [wiki articles](https://github.com/lat9/paypalr/wiki).

## Charging vaulted cards from custom code

When a customer pays by card, PayPal returns a `payment_source.card` element that includes the vaulted token. The module saves that response in the vault table via `PayPalRestful\Common\VaultManager::saveVaultedCard` and raises the `NOTIFY_PAYPALR_VAULT_CARD_SAVED` observer event so other plugins can react to the new or updated token.【F:includes/modules/payment/paypal/PayPalRestful/Common/VaultManager.php†L63-L151】【F:includes/modules/payment/paypalr.php†L2052-L2141】

Third-party integrations can use the stored information to perform subsequent charges (for example, recurring subscriptions) without asking the customer to re-enter their card details:

1. Include the payment module's autoloader before referencing any of its classes:

   ```php
   require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/pprAutoload.php';
   ```

2. Retrieve the customer's vaulted cards. The helper `paypalr::getVaultedCardsForCustomer($customers_id, $activeOnly = true)` returns an array of normalized records (vault identifier, status, masked digits, expiry, billing address, and the original `payment_source.card` payload). You can also call `PayPalRestful\Common\VaultManager::getCustomerVaultedCards` directly if you prefer.【F:includes/modules/payment/paypalr.php†L2559-L2561】【F:includes/modules/payment/paypal/PayPalRestful/Common/VaultManager.php†L168-L283】

3. Build a new PayPal order with the vaulted instrument. Instantiate `PayPalRestful\Api\PayPalRestfulApi` using the environment credentials returned by `paypalr::getEnvironmentInfo()`, create an order payload that references the stored `vault_id`, and then authorize or capture the order. A minimal example that immediately captures a payment looks like:

   ```php
   [$clientId, $clientSecret] = paypalr::getEnvironmentInfo();
   $api = new \PayPalRestful\Api\PayPalRestfulApi(MODULE_PAYMENT_PAYPALR_SERVER, $clientId, $clientSecret);

   $card = $vaultCards[0]; // Result from step 2

   $orderRequest = [
       'intent' => 'CAPTURE',
       'purchase_units' => [[
           'amount' => [
               'currency_code' => 'USD',
               'value' => '10.00',
           ],
       ]],
       'payment_source' => [
           'card' => [
               'vault_id' => $card['vault_id'],
               'expiry' => $card['expiry'],
               'last_digits' => $card['last_digits'],
               'billing_address' => $card['billing_address'],
               'attributes' => [
                   'stored_credential' => [
                       'payment_initiator' => 'MERCHANT',
                       'payment_type' => 'RECURRING',
                       'usage' => 'SUBSEQUENT',
                   ],
               ],
           ],
       ],
   ];

   $createResponse = $api->createOrder($orderRequest);
   $captureResponse = $api->captureOrder($createResponse['id']);
   ```

   Adjust the purchase units, intent, and stored credential attributes to match your billing use case. PayPal's [vault documentation](https://developer.paypal.com/docs/multiparty/seller/checkout/facilitator/vault/) describes additional optional fields such as previous network transaction references.

4. After PayPal returns the new capture or authorization, call `VaultManager::saveVaultedCard` with the updated `payment_source.card` element so the module records the `last_used` timestamp and any status changes for future reuse.【F:includes/modules/payment/paypal/PayPalRestful/Common/VaultManager.php†L63-L151】
