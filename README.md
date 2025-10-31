# PayPal Advanced Checkout Payment Module
This Zen Cart payment module (`paypalr`) combines the processing for the **PayPal Payments Pro** (`paypaldp`) and **PayPal Express Checkout** (`paypalwpp`) payment modules that are currently built into Zen Cart distributions. The PayPal Advanced Checkout plugin uses PayPal's now-current [REST APIs](https://developer.paypal.com/api/rest/) to replace the older NVP (**N**ame **V**alue **P**air) communication methods and unifies the two legacy modules into one.

> **Note:** This fork is branded as **PayPal Advanced Checkout** to distinguish it from Zen Cart's core PayPal REST API module and prevent versioning conflicts.

Zen Cart Support Thread (legacy PayPal RESTful): https://www.zen-cart.com/forumdisplay.php?170-PayPal-RESTful-support

Zen Cart Plugin Download Link: https://www.zen-cart.com/downloads.php?do=file&id=2382

**Zen Cart compatibility.** The payment module supports Zen Cart v1.5.7c and later, provided that the following requirements are met:

1. Apply the module's [required `order_total` notifier patch](https://github.com/lat9/paypalr/wiki/Required-changes-to-%60-includes-classes-order_total.php%60); this is the only change that touches a core file.
2. Use the bundled ObserverManager trait shim that this module installs automatically.
3. Use the bundled language shim that this module installs automatically.

Those shims provide the 1.5.8a trait and language fallbacks automatically&mdash;no additional downloads are needed beyond the `order_total` patch.

> :warning: Zen Cart 1.5.7c can be installed on hosts that still default to PHP 7.0 or 7.1. The module relies on PHP 7.1 language features such as nullable type hints and scalar return types (for example `PayPalRestful\Zc2Pp\CreatePayPalOrderRequest::resolveListenerEndpoint(?string $listener_endpoint)` and `PayPalRestful\Compatibility\TemplateFunc::determineTemplateDirectory(): ?string`). Running it on PHP 7.0 or older will trigger fatal parse errors similar to `unexpected '?'`. Upgrade the store's PHP version to at least 7.1 (PHP 7.4+ recommended) before enabling the module.【F:includes/modules/payment/paypal/PayPalRestful/Zc2Pp/CreatePayPalOrderRequest.php†L514-L520】【F:includes/modules/payment/paypal/PayPalRestful/Compatibility/TemplateFunc.php†L48-L105】

The module's operation has been validated …

1. With PHP versions 7.1 through 8.4; **PHP 7.0 or older will result in fatal PHP errors.**
2. In Zen Cart's 3-page checkout environment (v1.5.7**c**+, v2.0.x and v2.1.0)
3. With One-Page Checkout  (OPC), v2.4.6-2.5.3
   1. Using *OPC*'s guest-checkout feature.
   2. Both requiring confirmation and not!
4. With both the built-in responsive_classic and [ZCA Bootstrap](https://www.zen-cart.com/downloads.php?do=file&id=2191) (v3.6.2-3.7.7) templates.

For additional information, refer to the payment-module's [wiki articles](https://github.com/lat9/paypalr/wiki).

## Integrated sign-up (ISU)

Zen Cart's admin exposes a **Complete PayPal setup** button that now opens the Numinix onboarding portal. The bridge script collects your storefront metadata, generates a tracking reference and redirects administrators to [numinix.com](https://www.numinix.com/) where the secure PayPal flow runs. Once onboarding finishes, the portal returns administrators to the Payment Modules page so they can paste the issued credentials into the module configuration.

### Prerequisites

* Install the module and apply the required `order_total` notifier patch.
* Populate the storefront metadata that ISU sends to PayPal (store name, owner name, and owner email address) from Zen Cart's configuration.
* Ensure the store can establish outbound HTTPS requests so the onboarding portal can load successfully.

### Sandbox versus live

The module chooses the sandbox or live onboarding flow based on the **PayPal Server** setting (`MODULE_PAYMENT_PAYPALR_SERVER`). Sandbox flows let you test onboarding without touching production accounts. Live onboarding uses the production PayPal environment managed by Numinix and returns credentials that you enter manually.

### Partner attribution and redirects

The bridge forwards the partner attribution header automatically and includes the admin Payment Modules URL as the `redirect_url` parameter. When the portal finishes onboarding it redirects to `admin/paypalr_integrated_signup.php?action=return`, which then routes administrators back to the module configuration page with a status message. If the portal is closed early, `action=cancel` is triggered instead so the admin sees a reminder that onboarding was not completed.

## Digital wallet modules (Apple Pay, Google Pay, Venmo)

The plugin bundles standalone payment modules for Apple Pay, Google Pay, and Venmo so you can present the wallet buttons natively on the checkout payment page while reusing the core PayPal Advanced Checkout credentials. Each module extends the primary `paypalr` class and inherits its API configuration, logging, and vault logic.【F:includes/modules/payment/paypalr_applepay.php†L1-L151】【F:includes/modules/payment/paypalr_googlepay.php†L1-L151】【F:includes/modules/payment/paypalr_venmo.php†L1-L153】

1. Confirm that the base **PayPal Advanced Checkout** module is installed and enabled. The wallet modules disable themselves if `MODULE_PAYMENT_PAYPALR_VERSION` is not defined, which happens when the parent module is missing or removed.【F:includes/modules/payment/paypalr_applepay.php†L38-L57】【F:includes/modules/payment/paypalr_googlepay.php†L38-L57】【F:includes/modules/payment/paypalr_venmo.php†L40-L59】
2. Ask your PayPal account manager to enable Apple Pay, Google Pay, and/or Venmo for your merchant account. PayPal must approve each wallet before the buttons render live. If you support Apple Pay on the web today, reuse your existing Apple domain registration; otherwise upload the `.well-known/apple-developer-merchantid-domain-association` file that PayPal provides to the storefront root so Apple can validate the domain.
3. In **Modules → Payment**, install `PayPal Apple Pay`, `PayPal Google Pay`, or `PayPal Venmo`. Enable each module, assign a sort order, and (optionally) scope availability with the standard payment-zone selector. The configuration keys are `MODULE_PAYMENT_PAYPALR_<WALLET>_STATUS`, `MODULE_PAYMENT_PAYPALR_<WALLET>_SORT_ORDER`, and `MODULE_PAYMENT_PAYPALR_<WALLET>_ZONE` respectively.【F:includes/modules/payment/paypalr_applepay.php†L107-L152】【F:includes/modules/payment/paypalr_googlepay.php†L107-L152】【F:includes/modules/payment/paypalr_venmo.php†L109-L154】
4. Clear compiled templates or page caches so Zen Cart can inject the bundled JavaScript (`jquery.paypalr.applepay.js`, `jquery.paypalr.googlepay.js`, `jquery.paypalr.venmo.js`) during checkout. The scripts register placeholders, listen for PayPal’s wallet events, and push the confirmed payment source into hidden form fields that the module posts back to PayPal.【F:includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.applepay.js†L1-L32】【F:includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js†L1-L32】【F:includes/modules/payment/paypal/PayPalRestful/jquery.paypalr.venmo.js†L1-L32】
5. Run a sandbox checkout with each wallet to confirm that PayPal returns an approved status (the modules treat `COMPLETED`, `APPROVED`, and `AUTHORIZED` as success values). If PayPal responds with `PAYER_ACTION_REQUIRED`, the module surfaces the localized error message and prompts the customer to pick a different method.【F:includes/modules/payment/paypalr_applepay.php†L118-L151】【F:includes/modules/payment/paypalr_googlepay.php†L118-L151】【F:includes/modules/payment/paypalr_venmo.php†L120-L153】

When the wallet modules are enabled alongside the base PayPal Advanced Checkout method, Zen Cart renders one radio option per wallet plus the traditional card/PayPal button. Because each wallet inherits the parent module’s logging, any issues appear in the `/logs/paypalr/` directory with the wallet type noted in the message prefix.

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

## Subscriptions and recurring products

PayPal Advanced Checkout does not create subscription catalog entries inside Zen Cart automatically, but it exposes the PayPal Billing Plans and Subscriptions REST endpoints so you can build recurring offers without leaving the storefront. The helper lives in `PayPalRestful\Api\PayPalRestfulApi` and supports plan creation, activation, subscription lifecycle management, and cancellations via dedicated methods.【F:includes/modules/payment/paypal/PayPalRestful/Api/PayPalRestfulApi.php†L274-L341】

1. **Create a billing product and plan.** Use the PayPal developer dashboard or a short script that calls `$api->createPlan()` to define pricing, billing cycles, and trial periods. The API class requires the same credentials retrieved via `paypalr::getEnvironmentInfo()`, so you can reuse the bootstrap code from the vaulted-card example above. Activate the plan with `$api->activatePlan($planId)` once PayPal returns the identifier.【F:includes/modules/payment/paypal/PayPalRestful/Api/PayPalRestfulApi.php†L274-L301】
2. **Map the PayPal plan to a Zen Cart product.** Store the plan ID in a custom product field (for example, a product meta tag, an attribute, or a dedicated table) so your subscription purchase flow can retrieve it later. Update the product description to explain the billing frequency, renewal price, cancellation policy, and how the subscription appears on the customer’s PayPal account.
3. **Launch the subscription sign-up.** When the customer checks out, detect the subscription product in your cart observer, instantiate `PayPalRestfulApi`, and call `$api->createSubscription()` with the plan ID, subscriber details, and `application_context` return URLs that point to a dedicated confirmation page. Redirect the customer to the approval link PayPal returns so they can accept the billing agreement.【F:includes/modules/payment/paypal/PayPalRestful/Api/PayPalRestfulApi.php†L293-L341】
4. **Handle webhooks and order fulfillment.** Configure PayPal to send `BILLING.SUBSCRIPTION.ACTIVATED`, `PAYMENT.SALE.COMPLETED`, and related events to `ppr_webhook.php`. The webhook controller authenticates incoming messages and dispatches them to event handlers, giving you a single place to update order status, extend service periods, or flag delinquencies.【F:ppr_webhook.php†L1-L44】【F:includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookController.php†L1-L200】
5. **Let customers manage stored cards.** Direct subscribers to the optional saved-card management page (`account_saved_credit_cards`) so they can update billing addresses or remove expired cards. The page renders editable vault entries, validates address updates, and issues PATCH requests to PayPal when the customer edits a card.【F:includes/modules/pages/account_saved_credit_cards/header_php.php†L49-L775】

To pause or cancel an active subscription, call `$api->suspendSubscription()` or `$api->cancelSubscription()` with the PayPal subscription ID and an explanatory note. Reactivations use `$api->activateSubscription()`. Each helper validates the input identifier and sends the appropriate REST call, logging the response for audit purposes.【F:includes/modules/payment/paypal/PayPalRestful/Api/PayPalRestfulApi.php†L315-L341】
