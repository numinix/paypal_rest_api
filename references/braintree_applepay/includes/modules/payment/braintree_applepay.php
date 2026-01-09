<?php
// Include Braintree SDK and the shared common class
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/Braintree.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_common.php');

// Ensure language constants are available when the module is instantiated on the storefront.
if (!defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE') || !defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION')) {
    $languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'english';
    $languagePaths = [
        DIR_FS_CATALOG . DIR_WS_LANGUAGES . $languageCode . '/modules/payment/lang.braintree_applepay.php',
        DIR_FS_CATALOG . DIR_WS_LANGUAGES . $languageCode . '/modules/payment/braintree_applepay.php',
        DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/payment/lang.braintree_applepay.php',
        DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/payment/braintree_applepay.php'
    ];

    foreach ($languagePaths as $languageFile) {
        if (!file_exists($languageFile)) {
            continue;
        }

        $define = include $languageFile;

        if (is_array($define)) {
            foreach ($define as $key => $value) {
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }

        // Older language files will not return an array, so continue checking constants.
        if (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE') && defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION')) {
            break;
        }
    }

    if (!defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE')) {
        define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE', 'Apple Pay');
    }

    if (!defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION')) {
        define('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION', 'Pay with Apple Pay via Braintree.');
    }
}

class braintree_applepay {
    public $code = 'braintree_applepay';
    public $title;
    public $description;
    public $enabled;
    public $sort_order;
    public $zone;
    public $order_status;
    public $debug_logging;
    public $order;
    public $merchantAccountID;
    public $nonce;
    public $_check;
    private $debug_email_notifications = false;
    private $log_directory = null;
    private $use3DS = false;
    private $braintreeCommon;
    private $tokenizationKey = '';

    function __construct() {
        global $order;
        $this->order = $order; // assign global order if not already

        $this->code = 'braintree_applepay';
        $this->title = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE;
        $this->description = MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SORT_ORDER') ? MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SORT_ORDER : null;
        $this->enabled = defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS') && MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS == 'True';
        $this->zone = (int)(defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ZONE') ? MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ZONE : 0);

        $this->order_status = (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID') && MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID > 0)
            ? MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID
            : (isset($order->info['order_status']) ? $order->info['order_status'] : 0);

        $debugSetting = defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_DEBUGGING') ? MODULE_PAYMENT_BRAINTREE_APPLE_PAY_DEBUGGING : 'Alerts Only';
        $this->debug_logging = in_array($debugSetting, ['Log File', 'Log and Email']);
        $this->debug_email_notifications = ($debugSetting === 'Log and Email');
        $this->log_directory = $this->resolveLogDirectory();
        $this->use3DS = $this->getConfigFlag('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_USE_3DS');

        // Check if module is installed before running upgrade/license check
        if ($this->check() && $this->enabled && defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) {
            require_once(DIR_FS_ADMIN . '/includes/classes/numinix_plugins.php');
            $nx_plugin = new nxPluginLicCheck();
            $nx_plugin->nxPluginLicense('TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0FQUExFX1BBWV9WRVJTSU9O:YnJhaW50cmVlX2FwcGxlcGF5:TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0FQUExFX1BBWV9TVEFUVVM=:MTk2MA==:QnJhaW50cmVlIEFwcGxlIFBheSBmb3IgWmVuIENhcnQ=');
        }

        $config = [ 'debug_logging' => $this->debug_logging ];

        if (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOKENIZATION_KEY')) {
            $this->tokenizationKey = trim(MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOKENIZATION_KEY);
            if ($this->tokenizationKey !== '') {
                $config['tokenization_key'] = $this->tokenizationKey;
            }
        }

        if ($this->enabled) {
            $config = array_merge($config, [
                'environment' => MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SERVER,
                'merchant_id' => MODULE_PAYMENT_BRAINTREE_APPLE_PAY_MERCHANT_KEY,
                'public_key'  => MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PUBLIC_KEY,
                'private_key' => MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PRIVATE_KEY
            ]);
        }

        $this->braintreeCommon = new BraintreeCommon($config);

        $this->logDebug('Apple Pay module initialized', [
            'enabled'             => $this->enabled,
            'environment'         => defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SERVER') ? MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SERVER : null,
            'merchant_account_id' => $this->maskValue($this->merchantAccountID ?? ''),
            'currency'            => $_SESSION['currency'] ?? null,
            'debug_mode'          => $debugSetting,
            'use_three_ds'        => $this->use3DS
        ]);


        if ($this->enabled && (!defined('IS_ADMIN_FLAG') || !IS_ADMIN_FLAG)) {
            $this->merchantAccountID = $this->braintreeCommon->get_merchant_account_id($_SESSION['currency']);
            $this->logDebug('Resolved merchant account ID', [
                'currency'            => $_SESSION['currency'] ?? null,
                'merchant_account_id' => $this->maskValue($this->merchantAccountID)
            ]);
        } else {
            $this->merchantAccountID = null;
        }

        // Run update_status if an order object exists
        if (is_object($order)) {
            $this->update_status();
        }
    }

    function update_status() {
        global $order, $db;
        if ($this->enabled && (int)$this->zone > 0) {
            $check_flag = false;
            $sql = "SELECT zone_id
                    FROM " . TABLE_ZONES_TO_GEO_ZONES . "
                    WHERE geo_zone_id = :zoneId
                      AND zone_country_id = :countryId
                    ORDER BY zone_id";
            $sql = $db->bindVars($sql, ':zoneId', $this->zone, 'integer');
            $sql = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');
            $check = $db->Execute($sql);
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } else if ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }
            if (!$check_flag) {
                $this->enabled = false;
            }
        }
        
        // Additional check: do not allow orders over $10,000 USD.
        if ($this->enabled) {
            global $currencies;
            $order_amount_usd = (float)($order->info['total'] ?? 0);
            if (isset($currencies) && is_object($currencies)) {
                $order_amount_usd = $currencies->value($order_amount_usd, true, 'USD');
            }
            if ($order_amount_usd > 10000 || $order->info['total'] == 0) {
                $this->enabled = false;
            }
        }
    }

    /**
     * Generate the Braintree client token for Apple Pay.
     */
    function generate_client_token() {
        $merchantAccountId = isset($this->merchantAccountID) ? $this->merchantAccountID : null;
        $delays = [200000, 500000, 1000000]; // microseconds: 200ms, 500ms, 1s
        $lastException = null;

        // First attempt (no delay)
        try {
            $token = $this->braintreeCommon->generate_client_token($merchantAccountId);
            $this->logDebug('Generated client token', [
                'using_merchant_account' => (bool)$merchantAccountId
            ]);
            return $token;
        } catch (Exception $e) {
            $lastException = $e;
            $this->logDebug('Failed to generate client token (attempt 1)', [
                'using_merchant_account' => (bool)$merchantAccountId,
                'error'                  => $e->getMessage()
            ]);
        }

        // Retry attempts with delays
        foreach ($delays as $attemptNumber => $base) {
            $jitter = random_int(-(int)($base * 0.3), (int)($base * 0.3));
            usleep($base + $jitter);
            
            try {
                $token = $this->braintreeCommon->generate_client_token($merchantAccountId);
                $this->logDebug('Generated client token', [
                    'using_merchant_account' => (bool)$merchantAccountId,
                    'attempt'                => $attemptNumber + 2
                ]);
                return $token;
            } catch (Exception $e) {
                $lastException = $e;
                $this->logDebug('Failed to generate client token (attempt ' . ($attemptNumber + 2) . ')', [
                    'using_merchant_account' => (bool)$merchantAccountId,
                    'error'                  => $e->getMessage()
                ]);
            }
        }

        // All attempts failed, throw the last exception
        $this->logDebug('All attempts to generate client token failed', [
            'using_merchant_account' => (bool)$merchantAccountId,
            'total_attempts'         => count($delays) + 1
        ]);
        throw $lastException;
    }

    function selection() {
        global $order;

        if (!$this->enabled) {
            $this->logDebug('Selection requested while module disabled');
            return false;
        }
        $clientToken = (string) $this->generate_client_token();
        $tokenizationKey = $this->tokenizationKey;
        $hasTokenizationKey = ($tokenizationKey !== '');

        if ($clientToken === '' && $hasTokenizationKey && !$this->use3DS) {
            error_log('Braintree Apple Pay: Falling back to the configured tokenization key because a client token was not available.');
            $this->logDebug('Falling back to tokenization key for authorization');
        }

        if ($clientToken === '' && ($this->use3DS || !$hasTokenizationKey)) {
            $this->logDebug('Apple Pay unavailable due to missing client token', [
                'use_three_ds'     => $this->use3DS,
                'has_tokenization' => $hasTokenizationKey
            ]);

            return array(
                'id'     => $this->code,
                'module' => MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TEXT_ADMIN_TITLE,
                'fields' => array(
                    array(
                        'title' => '',
                        'field' => '<div style="color: red; font-weight: bold; padding: 1em; border: 1px solid red; background-color: #ffeeee;">'
                            . 'Incorrect Braintree Configuration. Please contact the store administrator.'
                            . '</div>'
                    )
                )
            );
        }

        $authorizationType = $clientToken !== '' ? 'clientToken' : ($hasTokenizationKey ? 'tokenizationKey' : '');
        $authorizationValue = $clientToken !== '' ? $clientToken : $tokenizationKey;

        $this->logDebug('Rendering Apple Pay selection block', [
            'order_total'   => isset($order->info['total']) ? (float)$order->info['total'] : null,
            'currency'      => $order->info['currency'] ?? ($_SESSION['currency'] ?? null),
            'use_three_ds'  => $this->use3DS,
            'authorization' => $authorizationType
        ]);
        $orderTotalsSelector = defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOTAL_SELECTOR') ? MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOTAL_SELECTOR : '#orderTotal';

        // Get the order total in the selected currency
        global $currencies;
        $selectedCurrency = $order->info['currency'] ?? ($_SESSION['currency'] ?? 'USD');
        $orderTotalAmount = (float)($order->info['total'] ?? 0);
        
        // Convert from default currency to selected currency if needed
        if (isset($currencies) && is_object($currencies)) {
            $orderTotalAmount = $currencies->value($orderTotalAmount, true, $selectedCurrency);
        }
        
        $config = array(
            'use3DS'             => (bool)$this->use3DS,
            'storeName'          => STORE_NAME,
            'orderTotal'         => number_format($orderTotalAmount, 2, '.', ''),
            'currencyCode'       => $selectedCurrency,
            'orderTotalsSelector'=> $orderTotalsSelector,
            'customerEmail'      => $order->customer['email_address'] ?? '',
            'authorizationToken' => $authorizationValue,
            'clientToken'        => $clientToken,
            'tokenizationKey'    => $tokenizationKey,
            'authorizationType'  => $authorizationType,
            'billing'            => array(
                'givenName'         => $order->billing['firstname'] ?? '',
                'surname'           => $order->billing['lastname'] ?? '',
                'phoneNumber'       => $order->customer['telephone'] ?? '',
                'streetAddress'     => $order->billing['street_address'] ?? '',
                'locality'          => $order->billing['city'] ?? '',
                'region'            => $order->billing['state'] ?? '',
                'postalCode'        => $order->billing['postcode'] ?? '',
                'countryCodeAlpha2' => $order->billing['country']['iso_code_2'] ?? ''
            )
        );

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        ob_start();
        ?>
        <style>
            .apple-pay-button {
                appearance: -apple-pay-button;
                -apple-pay-button-type: buy;
                -apple-pay-button-style: black;
                height: 44px;
                width: 100%;
                max-width: 400px;
                display: inline-block;
                margin: 0.5em 0;
                cursor: pointer;
            }

            #apple-pay-button-container p.apple-pay-unavailable {
                color: #666;
                font-size: 0.9em;
                margin: 0.5em 0;
            }
        </style>
        <div id="apple-pay-button-container"></div>
        <script>
        (function () {
            "use strict";

            window.applePayScriptsLoaded = window.applePayScriptsLoaded || false;

            const applePayConfig = <?php echo $configJson; ?>;
            const applePayState = window.__braintreeApplePayState = window.__braintreeApplePayState || {};
            const applePayDebugEnabled = true;

            if (typeof applePayState.setupPromise === "undefined") {
                applePayState.setupPromise = null;
            }
            if (typeof applePayState.initializationPromise === "undefined") {
                applePayState.initializationPromise = null;
            }
            if (typeof applePayState.scriptsPromise === "undefined") {
                applePayState.scriptsPromise = null;
            }
            if (typeof applePayState.domReadyHandler === "undefined") {
                applePayState.domReadyHandler = null;
            }
            if (typeof applePayState.reloadHandler === "undefined") {
                applePayState.reloadHandler = null;
            }
            if (typeof applePayState.lastAuthorizationToken === "undefined") {
                applePayState.lastAuthorizationToken = null;
            }
            if (typeof applePayState.lastAuthorizationType === "undefined") {
                applePayState.lastAuthorizationType = null;
            }

            function applePayDebugLog() {
                if (!applePayDebugEnabled || typeof console === "undefined" || typeof console.log !== "function") {
                    return;
                }
                const args = Array.prototype.slice.call(arguments);
                args.unshift("Apple Pay:");
                console.log.apply(console, args);
            }

            function applePayDebugWarn() {
                if (!applePayDebugEnabled || typeof console === "undefined") {
                    return;
                }
                const warn = typeof console.warn === "function" ? console.warn : console.log;
                const args = Array.prototype.slice.call(arguments);
                args.unshift("Apple Pay:");
                warn.apply(console, args);
            }

            function isSupportedBrowserForApplePay() {
                const ua = navigator.userAgent || "";
                
                // All iOS browsers use WebKit and support Apple Pay if ApplePaySession is available
                // This includes Safari, Chrome (CriOS), Firefox (FxiOS), Edge (EdgiOS), etc.
                const isIOS = /iPhone|iPad|iPod/.test(ua);
                
                if (!isIOS) {
                    // Non-iOS devices (like Windows, Android, macOS) - rely on ApplePaySession availability
                    applePayDebugLog("Non-iOS device detected; relying on ApplePaySession availability");
                    return true; // Let ApplePaySession check handle availability
                }
                
                // On iOS, all browsers use WebKit underneath and can support Apple Pay
                // The actual availability is determined by ApplePaySession.canMakePayments()
                applePayDebugLog("iOS browser detection", {
                    userAgent: ua,
                    isIOS: isIOS,
                    browserType: /CriOS/.test(ua) ? 'Chrome' : (/FxiOS/.test(ua) ? 'Firefox' : (/OPiOS/.test(ua) ? 'Opera' : (/EdgiOS/.test(ua) ? 'Edge' : 'Safari')))
                });
                
                // Return true and let ApplePaySession.canMakePayments() determine actual availability
                return true;
            }

            function getAuthorizationDetails() {
                const typeFromConfig = applePayConfig.authorizationType || "";
                const clientToken = typeof applePayConfig.clientToken === "string" ? applePayConfig.clientToken : "";
                const tokenizationKey = typeof applePayConfig.tokenizationKey === "string" ? applePayConfig.tokenizationKey : "";

                let type = typeFromConfig;
                if (!type) {
                    if (clientToken !== "") {
                        type = "clientToken";
                    } else if (tokenizationKey !== "") {
                        type = "tokenizationKey";
                    } else {
                        type = null;
                    }
                }

                let value = "";
                if (type === "clientToken" && clientToken !== "") {
                    value = clientToken;
                } else if (type === "tokenizationKey" && tokenizationKey !== "") {
                    value = tokenizationKey;
                } else if (!type && clientToken !== "") {
                    type = "clientToken";
                    value = clientToken;
                } else if (!type && tokenizationKey !== "") {
                    type = "tokenizationKey";
                    value = tokenizationKey;
                }

                return {
                    value: value || "",
                    type: type || null
                };
            }

            applePayDebugLog("Initializing inline Apple Pay controller");

            const applePayWrapperSelectors = [
                ".payment-method-item",
                ".payment-method",
                ".payment-method-option",
                ".payment-option",
                ".payment-option-item",
                ".custom-control",
                ".custom-radio",
                ".braintree_applepay",
                ".braintree-applepay"
            ];

            function getApplePayWrappers(elements) {
                const wrappers = [];
                const targets = Array.isArray(elements) ? elements : [elements];

                targets.forEach(function (el) {
                    if (!el) {
                        return;
                    }

                    applePayWrapperSelectors.forEach(function (selector) {
                        let wrapper = null;

                        if (typeof el.closest === "function") {
                            wrapper = el.closest(selector);
                        }

                        if (!wrapper) {
                            let current = el.parentElement;
                            while (current) {
                                if (current.matches && current.matches(selector)) {
                                    wrapper = current;
                                    break;
                                }
                                current = current.parentElement;
                            }
                        }

                        if (wrapper && wrappers.indexOf(wrapper) === -1) {
                            wrappers.push(wrapper);
                        }
                    });
                });

                return wrappers;
            }

            function restoreApplePayWrappers(container) {
                if (!container) {
                    return;
                }

                const wrappers = getApplePayWrappers([container]);
                wrappers.forEach(function (wrapper) {
                    const state = wrapper.dataset.applePayWrapperState;
                    if (state === "with-container") {
                        const originalDisplay = wrapper.dataset.applePayOriginalDisplay;
                        wrapper.style.display = typeof originalDisplay === "string" ? originalDisplay : "";
                        delete wrapper.dataset.applePayWrapperState;
                        delete wrapper.dataset.applePayOriginalDisplay;
                    }
                });
            }

            function hideApplePayOption(options) {
                options = options || {};
                const preserveContainer = options.preserveContainer === true;
                const hideWrapper = options.hideWrapper !== false;

                const radio = document.querySelector("input[type='radio'][name='payment'][value='braintree_applepay'], #pmt-braintree_applepay");
                if (radio) {
                    radio.style.display = "none";
                }

                const lbl = document.querySelector("label[for='pmt-braintree_applepay']");
                if (lbl) {
                    lbl.style.display = "none";
                }

                const container = document.getElementById("apple-pay-button-container");
                if (container && !preserveContainer) {
                    container.style.display = "none";
                }

                if (!hideWrapper) {
                    return;
                }

                const wrappers = getApplePayWrappers([container, lbl, radio]);
                wrappers.forEach(function (wrapper) {
                    if (!wrapper) {
                        return;
                    }

                    const containsContainer = container && wrapper.contains(container);
                    const shouldHide = !containsContainer || !preserveContainer;

                    if (!shouldHide) {
                        return;
                    }

                    if (typeof wrapper.dataset.applePayOriginalDisplay === "undefined") {
                        wrapper.dataset.applePayOriginalDisplay = wrapper.style.display || "";
                    }
                    wrapper.style.display = "none";
                    wrapper.dataset.applePayWrapperState = containsContainer ? "with-container" : "without-container";
                });
            }

            function loadApplePayScripts() {
                if (window.applePayScriptsLoaded) {
                    applePayDebugLog("Braintree JS already loaded");
                    return Promise.resolve();
                }

                if (applePayState.scriptsPromise) {
                    applePayDebugLog("Reusing in-flight Apple Pay script loader");
                    return applePayState.scriptsPromise;
                }

                const scripts = [
                    "https://js.braintreegateway.com/web/3.133.0/js/client.min.js",
                    "https://js.braintreegateway.com/web/3.133.0/js/apple-pay.min.js"
                ];
                if (applePayConfig.use3DS) {
                    scripts.push("https://js.braintreegateway.com/web/3.133.0/js/three-d-secure.min.js");
                } else {
                    applePayDebugLog("3DS disabled via module settings; skipping Braintree 3DS resources");
                    delete window.bt3DS;
                }

                const loadScript = function (src) {
                    return new Promise(function (resolve, reject) {
                        const existing = document.querySelector('script[src="' + src + '"]');
                        if (existing) {
                            applePayDebugLog("Script already present", src);
                            // Wait a tick to ensure it's executed
                            setTimeout(resolve, 10);
                            return;
                        }

                        const script = document.createElement("script");
                        script.src = src;
                        script.onload = function () {
                            applePayDebugLog("Loaded script", src);
                            // Wait a tick to ensure script is executed
                            setTimeout(resolve, 10);
                        };
                        script.onerror = function () {
                            applePayDebugWarn("Failed to load script", src);
                            reject(new Error("Failed to load script: " + src));
                        };
                        document.body.appendChild(script);
                    });
                };

                applePayDebugLog("Loading Braintree JS resources", scripts);

                // Load scripts sequentially to ensure proper dependency order
                const scriptsPromise = scripts.reduce(function (promise, src) {
                    return promise.then(function () {
                        return loadScript(src);
                    });
                }, Promise.resolve()).then(function () {
                    // Verify braintree global is available
                    if (typeof braintree === "undefined" || !braintree.client || !braintree.applePay) {
                        applePayDebugWarn("Braintree scripts loaded but global objects not available");
                        throw new Error("Braintree SDK not properly loaded");
                    }
                    applePayDebugLog("All Braintree scripts loaded and verified");
                    window.applePayScriptsLoaded = true;
                }).catch(function (err) {
                    applePayDebugWarn("One or more Apple Pay resources failed to load", err);
                    throw err;
                }).finally(function () {
                    applePayState.scriptsPromise = null;
                });

                applePayState.scriptsPromise = scriptsPromise;
                return scriptsPromise;
            }

            function toPromise(result) {
                if (result && typeof result.then === "function") {
                    return result;
                }
                return Promise.resolve(result);
            }

            function checkBasicApplePayAvailability() {
                if (typeof ApplePaySession !== "undefined" && typeof ApplePaySession.canMakePayments === "function") {
                    try {
                        return toPromise(ApplePaySession.canMakePayments()).then(function (result) {
                            applePayDebugLog("ApplePaySession.canMakePayments resolved", result);
                            return !!result;
                        }).catch(function (err) {
                            applePayDebugWarn("canMakePayments rejected", err);
                            return false;
                        });
                    } catch (err) {
                        applePayDebugWarn("canMakePayments threw an error", err);
                        return Promise.resolve(false);
                    }
                }
                applePayDebugLog("ApplePaySession.canMakePayments not available; assuming true");
                return Promise.resolve(true);
            }

            function checkApplePayAvailability() {
                // First check if we're in a supported browser
                if (!isSupportedBrowserForApplePay()) {
                    applePayDebugWarn("Browser does not support Apple Pay");
                    return Promise.resolve(false);
                }

                if (typeof ApplePaySession === "undefined") {
                    applePayDebugLog("ApplePaySession undefined; Apple Pay unavailable");
                    return Promise.resolve(false);
                }

                // The basic availability check using ApplePaySession.canMakePayments() is sufficient
                // for determining Apple Pay support on both iOS and macOS platforms.
                return checkBasicApplePayAvailability();
            }

            function initializeApplePay() {
                applePayDebugLog("initializeApplePay invoked");
                const container = document.getElementById("apple-pay-button-container");
                if (!container) {
                    applePayDebugWarn("Apple Pay button container not found");
                    hideApplePayOption();
                    return Promise.resolve();
                }

                restoreApplePayWrappers(container);

                const authorizationDetails = getAuthorizationDetails();
                const authorization = authorizationDetails.value;
                const authorizationType = authorizationDetails.type;

                if (!authorization) {
                    applePayDebugLog("Authorization token missing; deferring initialization");
                    hideApplePayOption({ preserveContainer: true });
                    return Promise.resolve();
                }

                if (applePayState.initializationPromise) {
                    applePayDebugLog("Initialization already in progress; reusing promise");
                    return applePayState.initializationPromise;
                }

                applePayState.initializationInProgress = true;

                container.innerHTML = "";
                container.style.display = "";
                delete container.dataset.applePayButtonReady;

                const initializationPromise = checkApplePayAvailability().then(function (canUseApplePay) {
                    applePayDebugLog("checkApplePayAvailability resolved", canUseApplePay);
                    if (!canUseApplePay) {
                        applePayDebugWarn("Apple Pay not available on this device/browser");
                        hideApplePayOption();
                        throw new Error("APPLE_PAY_UNAVAILABLE");
                    }

                    return ensureClientInstance(authorizationDetails).then(function (clientInstance) {
                        return setupThreeDS(clientInstance, authorizationDetails).then(function () {
                            return ensureApplePayInstance(clientInstance);
                        });
                }).then(function (applePayInstance) {
                    if (!applePayInstance) {
                        applePayDebugWarn("Apple Pay instance not returned");
                        return;
                    }

                    return handleApplePayInstance(container, applePayInstance);
                });
                }).catch(function (err) {
                    if (err && err.message === "APPLE_PAY_UNAVAILABLE") {
                        return;
                    }
                    applePayDebugWarn("Error initializing Apple Pay", err);
                    hideApplePayOption({ preserveContainer: true });
                    container.style.display = "block";
                    restoreApplePayWrappers(container);
                    const msg = document.createElement("p");
                    msg.className = "apple-pay-unavailable";
                    msg.textContent = "Apple Pay is temporarily unavailable. Please choose another payment method.";
                    container.innerHTML = "";
                    container.appendChild(msg);
                }).finally(function () {
                    applePayState.initializationInProgress = false;
                    applePayState.initializationPromise = null;
                });

                applePayState.initializationPromise = initializationPromise;
                return initializationPromise;
            }

            function ensureClientInstance(authorizationDetails) {
                const authorization = authorizationDetails.value;
                const authorizationType = authorizationDetails.type;

                if (applePayState.lastAuthorizationToken !== authorization || applePayState.lastAuthorizationType !== authorizationType) {
                    applePayDebugLog("Authorization token changed; clearing cached Braintree instances");
                    applePayState.lastAuthorizationToken = authorization;
                    applePayState.lastAuthorizationType = authorizationType;
                    applePayState.clientInstance = null;
                    applePayState.applePayInstance = null;
                }

                if (applePayState.clientInstance) {
                    applePayDebugLog("Reusing cached Braintree client instance");
                    return Promise.resolve(applePayState.clientInstance);
                }

                return braintree.client.create({ authorization: authorization }).then(function (clientInstance) {
                    applePayDebugLog("Braintree client created");
                    applePayState.clientInstance = clientInstance;
                    return clientInstance;
                });
            }

            function ensureApplePayInstance(clientInstance) {
                if (applePayState.applePayInstance) {
                    applePayDebugLog("Reusing cached Braintree Apple Pay instance");
                    return Promise.resolve(applePayState.applePayInstance);
                }

                applePayDebugLog("Creating Braintree Apple Pay instance");
                return braintree.applePay.create({ client: clientInstance }).then(function (applePayInstance) {
                    applePayState.applePayInstance = applePayInstance;
                    return applePayInstance;
                });
            }

            function setupThreeDS(clientInstance, authorizationDetails) {
                if (!applePayConfig.use3DS) {
                    applePayDebugLog("3DS setup skipped (disabled)");
                    delete window.bt3DS;
                    return Promise.resolve();
                }

                if (!authorizationDetails || authorizationDetails.type !== "clientToken") {
                    applePayDebugWarn("3DS requires a client token; skipping setup because a tokenization key is in use.");
                    delete window.bt3DS;
                    return Promise.resolve();
                }

                applePayDebugLog("Setting up 3DS");
                if (window.bt3DS && typeof window.bt3DS.teardown === "function") {
                    applePayDebugLog("Tearing down previous 3DS instance");
                    window.bt3DS.teardown();
                    delete window.bt3DS;
                }

                return braintree.threeDSecure.create({
                    client: clientInstance,
                    version: 2
                }).then(function (threeDSecureInstance) {
                    window.bt3DS = threeDSecureInstance;
                }).catch(function (threeDSError) {
                    applePayDebugWarn("Failed to setup 3DS", threeDSError);
                    throw threeDSError;
                });
            }

            function handleApplePayInstance(container, applePayInstance) {
                // Always render the button if we get here - device compatibility is already verified
                // by ApplePaySession.canMakePayments() in checkBasicApplePayAvailability().
                // We don't check applePayInstance.canMakePayments() because:
                // 1. It can incorrectly return false on macOS even when Apple Pay is available
                // 2. Users without cards can add them through the Apple Pay modal when they click
                applePayDebugLog("Rendering Apple Pay button (device is compatible)");
                renderApplePayButton(container, applePayInstance);
                return Promise.resolve();
            }

            function renderApplePayButton(container, applePayInstance) {
                container.innerHTML = "";
                container.style.display = "";

                const applePayInputsForRender = Array.from(new Set(Array.from(document.querySelectorAll("input[name='payment'][value='braintree_applepay'], #pmt-braintree_applepay"))));
                applePayInputsForRender.forEach(function (input) {
                    if (input) {
                        input.style.display = "";
                    }
                });

                const applePayLabelForRender = document.querySelector("label[for='pmt-braintree_applepay']");
                if (applePayLabelForRender) {
                    applePayLabelForRender.style.display = "";
                }

                const button = document.createElement("button");
                button.className = "apple-pay-button";
                button.type = "button";
                button.addEventListener("click", function () {
                    const applePayRadio = document.querySelector("input[type='radio'][name='payment'][value='braintree_applepay'], #pmt-braintree_applepay");
                    if (applePayRadio) {
                        applePayRadio.checked = true;
                        applePayRadio.dispatchEvent(new Event("change"));
                    }
                    const paymentRequest = applePayInstance.createPaymentRequest({
                        total: {
                            label: applePayConfig.storeName,
                            amount: applePayConfig.orderTotal,
                            type: 'final'
                        },
                        currencyCode: applePayConfig.currencyCode,
                        requiredBillingContactFields: ["postalAddress", "name"]
                    });

                    applePayDebugLog("Creating ApplePaySession", paymentRequest);

                    const session = new ApplePaySession(3, paymentRequest);

                    session.onvalidatemerchant = function (event) {
                        applePayDebugLog("onvalidatemerchant triggered", event.validationURL);
                        applePayInstance.performValidation({
                            validationURL: event.validationURL,
                            displayName: applePayConfig.storeName
                        }).then(function (merchantSession) {
                            applePayDebugLog("Merchant validation complete");
                            session.completeMerchantValidation(merchantSession);
                        }).catch(function (err) {
                            applePayDebugWarn("performValidation failed", err);

                            if (err && typeof err.message === "string") {
                                const normalizedMessage = err.message.toLowerCase();
                                const hostname = window.location && window.location.hostname ? window.location.hostname : "<unknown>";
                                if (
                                    normalizedMessage.indexOf("domain") !== -1 &&
                                    (
                                        normalizedMessage.indexOf("not registered") !== -1 ||
                                        normalizedMessage.indexOf("not configured") !== -1 ||
                                        normalizedMessage.indexOf("invalid") !== -1
                                    )
                                ) {
                                    applePayDebugWarn(
                                        "Braintree rejected the merchant validation for this domain.",
                                        {
                                            hint: "Confirm '" + hostname + "' is added as an Apple Pay domain in the Braintree Control Panel.",
                                            originalMessage: err.message
                                        }
                                    );
                                }
                            }

                            session.abort();
                        });
                    };

                    session.onpaymentauthorized = function (event) {
                        applePayDebugLog("onpaymentauthorized triggered");
                        applePayInstance.tokenize({ token: event.payment.token }).then(function (payload) {
                            const submitNonce = function (nonce) {
                                const form = document.getElementById("checkout_payment")
                                    || document.querySelector("form[name='checkout_payment']");
                                if (!form) {
                                    alert("Checkout form not found!");
                                    return;
                                }

                                const input = document.createElement("input");
                                input.type = "hidden";
                                input.name = "payment_method_nonce";
                                input.value = nonce;
                                form.appendChild(input);
                                form.submit();
                            };

                            if (applePayConfig.use3DS && window.bt3DS && typeof window.bt3DS.verifyCard === "function") {
                                applePayDebugLog("Initiating 3DS verification");
                                if (!payload.details || !payload.details.bin) {
                                    applePayDebugWarn("Apple Pay 3DS skipped: Missing BIN from tokenized payload.");
                                    session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                    submitNonce(payload.nonce);
                                    return;
                                }

                                window.bt3DS.verifyCard({
                                    amount: applePayConfig.orderTotal,
                                    nonce: payload.nonce,
                                    bin: payload.details.bin,
                                    email: applePayConfig.customerEmail,
                                    billingAddress: applePayConfig.billing,
                                    onLookupComplete: function (data, next) {
                                        next();
                                    }
                                }).then(function (verification) {
                                    applePayDebugLog("3DS verification complete", verification);
                                    session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                    if (verification && verification.nonce) {
                                        submitNonce(verification.nonce);
                                    } else {
                                        alert("3D Secure verification did not return a valid nonce.");
                                    }
                                }).catch(function (err) {
                                    applePayDebugWarn("3DS verification failed", err);
                                    session.completePayment(ApplePaySession.STATUS_FAILURE);
                                    alert("3D Secure authentication failed. Please try again.");
                                });
                            } else {
                                applePayDebugLog("Skipping 3DS verification (disabled or unavailable)");
                                session.completePayment(ApplePaySession.STATUS_SUCCESS);
                                submitNonce(payload.nonce);
                            }
                        }).catch(function (tokenizeErr) {
                            applePayDebugWarn("Tokenization failed", tokenizeErr);
                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                        });
                    };

                    try {
                        session.begin();
                        applePayDebugLog("ApplePaySession begun");
                    } catch (err) {
                        applePayDebugWarn("Failed to begin ApplePaySession", err);
                        alert("Unable to start Apple Pay. Please ensure your browser and device support Apple Pay.");
                    }
                });

                restoreApplePayWrappers(container);
                container.appendChild(button);
                container.dataset.applePayButtonReady = "true";
                applePayDebugLog("Apple Pay button rendered");

                const applePayRadio = document.querySelector("input[type='radio'][name='payment'][value='braintree_applepay'], #pmt-braintree_applepay");
                if (applePayRadio && !applePayRadio.dataset.applePayBound) {
                    applePayRadio.addEventListener("click", function () {
                        button.click();
                    });
                    applePayRadio.dataset.applePayBound = "true";
                }
            }

            function initApplePayObserver() {
                if (applePayState.reloadHandler) {
                    document.removeEventListener("onePageCheckoutReloaded", applePayState.reloadHandler);
                }

                const reloadHandler = function () {
                    applePayDebugLog("onePageCheckoutReloaded detected; reinitializing");
                    prepareApplePaySetup();
                };
                document.addEventListener("onePageCheckoutReloaded", reloadHandler);
                applePayState.reloadHandler = reloadHandler;

                if (applePayState.mutationObserver) {
                    applePayState.mutationObserver.disconnect();
                    delete applePayState.mutationObserver;
                }

                const selector = applePayConfig.orderTotalsSelector;
                const target = selector ? document.querySelector(selector) : null;
                if (target && target.parentElement && typeof MutationObserver === "function") {
                    const parent = target.parentElement;
                    const observer = new MutationObserver(function () {
                        if (applePayState.initializationInProgress) {
                            return;
                        }

                        const container = document.getElementById("apple-pay-button-container");
                        if (!container || container.children.length > 0) {
                            return;
                        }

                        applePayDebugLog("MutationObserver detected empty Apple Pay container; scheduling reinitialization");
                        setTimeout(function () {
                            initializeApplePay();
                        }, 200);
                    });
                    observer.observe(parent, { childList: true, subtree: true });
                    applePayDebugLog("MutationObserver attached to parent of", selector);
                    applePayState.mutationObserver = observer;
                }
            }

            function initSubmitButtonToggle() {
                const form = document.querySelector("form[name='checkout_payment']");
                if (!form) return;
                const submitBtn = form.querySelector("button[type='submit']");
                if (!submitBtn) return;
                const applePayRadio = form.querySelector("input[type='radio'][name='payment'][value='braintree_applepay'], #pmt-braintree_applepay");
                const paymentRadios = form.querySelectorAll("input[type='radio'][name='payment']");
                if (!applePayRadio || paymentRadios.length === 0) return;

                function toggle() {
                    if (applePayRadio.checked) {
                        submitBtn.style.display = "none";
                    } else {
                        submitBtn.style.display = "";
                    }
                }

                paymentRadios.forEach(function (radio) {
                    radio.addEventListener("change", toggle);
                });

                toggle();
            }

            function prepareApplePaySetup() {
                const applePayRadio = document.querySelector("input[name='payment'][value='braintree_applepay'], #pmt-braintree_applepay");
                if (applePayRadio && !applePayRadio.dataset.applePayPrepared) {
                    applePayRadio.style.display = "";
                    const applePayLabel = document.querySelector("label[for='pmt-braintree_applepay']");
                    if (applePayLabel) {
                        applePayLabel.style.display = "";
                    }
                    const container = document.getElementById("apple-pay-button-container");
                    if (container) {
                        restoreApplePayWrappers(container);
                    }
                    applePayRadio.addEventListener("click", function () {
                        const btn = document.querySelector("#apple-pay-button-container button");
                        if (btn) {
                            btn.click();
                        }
                    });
                    applePayRadio.dataset.applePayPrepared = "true";
                }

                applePayDebugLog("Preparing Apple Pay setup");
                initSubmitButtonToggle();

                if (applePayState.setupPromise) {
                    applePayDebugLog("Setup already in progress; reusing promise");
                    return applePayState.setupPromise;
                }

                if (applePayState.initializationPromise) {
                    applePayDebugLog("Initialization already in progress; waiting for completion");
                    return applePayState.initializationPromise;
                }

                const setupPromise = loadApplePayScripts().then(function () {
                    applePayDebugLog("Braintree scripts ready; initializing Apple Pay");
                    return initializeApplePay();
                }).catch(function (err) {
                    applePayDebugWarn("Apple Pay setup failed", err);
                    const namedRadio = document.querySelector("input[type='radio'][name='payment'][value='braintree_applepay']");
                    const fallbackRadio = document.getElementById("pmt-braintree_applepay");
                    if (namedRadio) {
                        namedRadio.style.display = "none";
                    }
                    if (fallbackRadio && fallbackRadio !== namedRadio) {
                        fallbackRadio.style.display = "none";
                    }
                    const label = document.querySelector("label[for='pmt-braintree_applepay']");
                    if (label) {
                        label.style.display = "none";
                    }
                    const container = document.getElementById("apple-pay-button-container");
                    if (container) {
                        container.style.display = "block";
                        container.innerHTML = "";
                        const msg = document.createElement("p");
                        msg.className = "apple-pay-unavailable";
                        msg.textContent = "Apple Pay is temporarily unavailable. Please choose another payment method.";
                        container.appendChild(msg);
                    }
                }).finally(function () {
                    initApplePayObserver();
                    applePayState.setupPromise = null;
                });

                applePayState.setupPromise = setupPromise;
                return setupPromise;
            }

            if (applePayState.domReadyHandler) {
                document.removeEventListener("DOMContentLoaded", applePayState.domReadyHandler);
            }

            const domReadyHandler = function () {
                prepareApplePaySetup();
            };

            applePayState.domReadyHandler = domReadyHandler;

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", domReadyHandler);
            } else {
                domReadyHandler();
            }
        })();
        </script>
        <?php
        $output = ob_get_clean();

        return array(
            'id'     => $this->code,
            'module' => $this->title,
            'fields' => array(array('title' => '', 'field' => $output))
        );

    }

    function javascript_validation() {
        return false;
    }

    function pre_confirmation_check() {
        global $messageStack;

        $nonce = '';
        if (!empty($_POST['payment_method_nonce'])) {
            $nonce = $_POST['payment_method_nonce'];
        } elseif (!empty($_SESSION['payment_method_nonce'])) {
            $nonce = $_SESSION['payment_method_nonce'];
        }

        if ($nonce === '') {
            $messageStack->add_session('checkout_payment', MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PAYMENT_FAILED, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            return;
        }

        $_POST['payment_method_nonce'] = $_SESSION['payment_method_nonce'] = $nonce;
        $this->nonce = $nonce;
    }

    function confirmation() {
        return false;
    }

    function process_button() {
        return zen_draw_hidden_field('payment_method_nonce', $_POST['payment_method_nonce']);
    }

    function process_button_ajax() {
        global $order;
        $processButton = [
            'ccFields' => [],
            'extraFields' => [zen_session_name() => zen_session_id()]
        ];
        if (!empty($_POST['payment_method_nonce'])) {
            $processButton['ccFields']['bt_nonce'] = $_POST['payment_method_nonce']; // Apple Pay nonce
            $processButton['ccFields']['bt_payment_type'] = 'apple_pay';
            $processButton['ccFields']['bt_currency_code'] = $order->info['currency'];
            $this->logDebug('Captured Apple Pay nonce for processing', [
                'currency' => $order->info['currency'] ?? null
            ]);
        } else {
            $this->logDebug('process_button_ajax invoked without nonce in POST');
        }
        return $processButton;
    }

    /**
     * In before_process, delegate payment processing to the common class.
     */
    function before_process() {
        $this->logDebug('before_process triggered', [
            'merchant_account_id' => $this->maskValue($this->merchantAccountID),
            'submit_for_settlement' => (MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT == 'true')
        ]);
        return $this->braintreeCommon->before_process_common($this->merchantAccountID, array(), (MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT == 'true'));
    }

    /**
     * After the transaction is processed, update order status history and store transaction details.
     * Uses the unpaid status for authorize-only transactions and paid status for captured transactions.
     */
    public function after_process() {
            global $insert_id, $db, $order;

            $txnId = $_SESSION['braintree_transaction_id'] ?? '';
            $paymentStatus = $_SESSION['braintree_payment_status'] ?? 'Pending';
            $cardType = $_SESSION['braintree_card_type'] ?? 'Unknown';
            $currency = $_SESSION['braintree_currency'] ?? '';
            $amount = $_SESSION['braintree_amount'] ?? 0;

            if ($txnId) {
                    // Determine if this was an authorize-only or authorize-and-capture transaction
                    $isSettlement = (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT') && MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT == 'true');
                    
                    // Select appropriate order status based on settlement setting
                    if ($isSettlement) {
                        // Authorize and Capture - use paid status
                        $statusId = (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID > 0)
                            ? (int)MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID
                            : (int)$this->order_status;
                    } else {
                        // Authorize Only - use unpaid status
                        $statusId = (defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID') && (int)MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID > 0)
                            ? (int)MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID
                            : (int)$this->order_status;
                    }
                    
                    // Ensure we have a valid status ID
                    if ($statusId <= 0) {
                        $statusId = (int)$this->order_status;
                    }

                    $this->logDebug('Recording Apple Pay transaction details', [
                        'order_id'       => (int)$insert_id,
                        'transaction_id' => $this->maskValue($txnId, 6),
                        'status'         => $paymentStatus,
                        'amount'         => (float)$amount,
                        'currency'       => $currency,
                        'settlement'     => $isSettlement,
                        'status_id'      => $statusId
                    ]);
                    
                    // Update order status
                    $db->Execute("UPDATE " . TABLE_ORDERS . "
                                                SET orders_status = " . $statusId . "
                                                WHERE orders_id = " . (int)$insert_id);

                    // Insert into order history
                    $sql_data_array = [
                            'orders_id'         => (int)$insert_id,
                            'orders_status_id'  => $statusId,
                            'date_added'        => 'now()',
                            'comments'          => "Braintree Transaction ID: " . $txnId,
                            'customer_notified' => 1
                    ];
                    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

                    // Insert transaction into Braintree table
                    $braintree_order = [
                            'order_id'        => (int)$insert_id,
                            'txn_id'          => $txnId,
                            'txn_type'        => 'sale',
                            'module_name'     => 'braintree_applepay',
                            'payment_type'    => $cardType,
                            'payment_status'  => $paymentStatus,
                            'settle_amount'   => (float)$amount,
                            'settle_currency' => $currency,
                            'date_added'      => date('Y-m-d'),
                            'module_mode'     => 'apple_pay'
                    ];
                    zen_db_perform(TABLE_BRAINTREE, $braintree_order);
            }

            // Cleanup session variables
            unset($_SESSION['braintree_transaction_id']);
            unset($_SESSION['braintree_payment_status']);
            unset($_SESSION['braintree_card_type']);
            unset($_SESSION['braintree_currency']);
            unset($_SESSION['braintree_amount']);

            $this->logDebug('after_process cleanup complete', [
                'order_id' => (int)$insert_id
            ]);
    }

    // The following functions delegate to the common class.

    function getTransactionId($orderId) {
        return $this->braintreeCommon->getTransactionId($orderId);
    }

    function _GetTransactionDetails($oID) {
        $this->logDebug('Fetching transaction details', [
            'order_id' => (int)$oID
        ]);
        return $this->braintreeCommon->_GetTransactionDetails($oID);
    }

    function _doRefund($oID, $amount = 'Full', $note = '') {
        global $messageStack;
        try {
            $this->logDebug('Attempting refund', [
                'order_id' => (int)$oID,
                'amount'   => $amount,
                'note_set' => !empty($note)
            ]);
            // Call the refund function from the common class
            $success = $this->braintreeCommon->_doRefund($oID, $amount, $note);

            if (!$success) {
                throw new Exception("Refund processing failed.");
            }

            $messageStack->add_session("Refund processed successfully for Order #{$oID}", 'success');
            $this->logDebug('Refund processed successfully', [
                'order_id' => (int)$oID,
                'amount'   => $amount
            ]);
        } catch (Exception $e) {
            $messageStack->add_session("Refund Error: " . $e->getMessage(), 'error');
            $this->logDebug('Refund failed', [
                'order_id' => (int)$oID,
                'amount'   => $amount,
                'error'    => $e->getMessage()
            ]);
        }

        zen_redirect(zen_href_link(FILENAME_ORDERS, 'oID=' . $oID . '&action=edit', 'NONSSL'));
    }

    /**
     * _doCapt
     *
     * Captures an authorized payment from the admin interface.
     * Uses the configuration value for the paid order status.
     *
     * @param int $order_id The order ID.
     * @return bool True on successful capture, false otherwise.
     */
    function _doCapt($order_id) {
        $this->logDebug('Attempting capture', [
            'order_id' => (int)$order_id
        ]);
        return $this->braintreeCommon->capturePayment(
            $order_id,
            MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID, // Use the module-specific paid status configuration
            $this->code
        );
    }

    function install() {
        global $db;
        require_once(DIR_FS_ADMIN . '/includes/classes/numinix_plugins.php');

        $nx_plugin = new nxPluginLicCheck();
        $nx_plugin->nxPluginLicense('TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0FQUExFX1BBWV9WRVJTSU9O:YnJhaW50cmVlX2FwcGxlcGF5:TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0FQUExFX1BBWV9TVEFUVVM=:MTk2MA==:QnJhaW50cmVlIEFwcGxlIFBheSBmb3IgWmVuIENhcnQ=');
        // Ensure the Braintree table exists via the common class.
        $this->braintreeCommon->create_braintree_table();
    }

    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function remove() {
        global $db, $messageStack;
        $keys = implode("', '", $this->keys());
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('$keys')");
        $messageStack->add_session(NOTIFY_PAYMENT_BRAINTREE_UNINSTALLED, 'success');
    }

    function keys() {
        return [
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_VERSION',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SERVER',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_MERCHANT_KEY',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PUBLIC_KEY',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PRIVATE_KEY',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOKENIZATION_KEY',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SETTLEMENT',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_USE_3DS',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ORDER_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_UNPAID_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_TOTAL_SELECTOR',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SHOPPING_CART',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_PRODUCT_PAGE',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_CONFIRM_REDIRECT',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_DEBUGGING',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_SORT_ORDER',
            'MODULE_PAYMENT_BRAINTREE_APPLE_PAY_ZONE'
        ];
    }

    function admin_notification($zf_order_id) {
        if (!defined('MODULE_PAYMENT_BRAINTREE_APPLE_PAY_STATUS')) {
            return '';
        }
        global $db;
        $module = $this->code;
        $output = '';
        $response = $this->_GetTransactionDetails($zf_order_id);
        if (file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_admin_notification.php')) {
            include_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_admin_notification.php';
        }
        return $output;
    }

    private function resolveLogDirectory() {
        if (defined('DIR_FS_LOGS')) {
            return rtrim(DIR_FS_LOGS, '/\\') . '/';
        }

        if (defined('DIR_FS_CATALOG')) {
            return rtrim(DIR_FS_CATALOG, '/\\') . '/logs/';
        }

        return __DIR__ . '/../../../../logs/';
    }

    private function getConfigFlag($constantName, $default = false) {
        if (!defined($constantName)) {
            return $default;
        }

        $value = constant($constantName);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default;
            }

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return ((int)$value) === 1;
        }

        return (bool)$value;
    }

    private function logDebug($message, array $context = []) {
        if (!$this->debug_logging) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = '[' . $timestamp . '] ' . $message;
        if (!empty($context)) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $logEntry .= ' ' . $encoded;
            }
        }
        $logEntry .= PHP_EOL;

        if (!is_dir($this->log_directory)) {
            @mkdir($this->log_directory, 0775, true);
        }

        $logFile = $this->log_directory . 'braintree_applepay-' . date('Ymd') . '.log';
        @file_put_contents($logFile, $logEntry, FILE_APPEND);

        if ($this->debug_email_notifications && function_exists('zen_mail')) {
            $storeEmail = defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            if ($storeEmail !== '') {
                $subject = 'Braintree Apple Pay Debug Notice';
                $storeName = defined('STORE_NAME') ? STORE_NAME : 'Store';
                zen_mail($storeName, $storeEmail, $subject, $logEntry, $storeName, $storeEmail, array(), 'debug');
            }
        }
    }

    private function maskValue($value, $visible = 4) {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = (string)$value;
        $length = strlen($value);
        if ($length <= $visible) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $visible) . substr($value, -$visible);
    }

    /**
     * Calculate order amount based on currency conversion.
     * Converts from the default store currency to the target currency.
     * 
     * @param float $amount Amount in the default store currency
     * @param string $targetCurrency Target currency code (e.g., 'USD', 'EUR')
     * @param bool $applyFormatting Whether to apply formatting (deprecated parameter)
     * @return float Amount converted to target currency
     */
    function calc_order_amount($amount, $targetCurrency, $applyFormatting = false) {
        global $currencies;
        
        // If target is default currency, no conversion needed
        if (strtoupper(DEFAULT_CURRENCY) === strtoupper($targetCurrency)) {
            return round($amount, 2);
        }
        
        // Convert from default currency to target currency
        // Formula: (amount / rate_from) * rate_to
        $fromRate = $currencies->get_value(DEFAULT_CURRENCY);
        $toRate = $currencies->get_value($targetCurrency);
        
        if ($fromRate > 0) {
            $amount = ($amount / $fromRate) * $toRate;
        }
        
        // Handle currencies with no decimal places (like JPY)
        if ($targetCurrency == 'JPY' || (int)$currencies->get_decimal_places($targetCurrency) == 0) {
            $amount = (int)$amount;
        }
        
        return round($amount, 2);
    }
}
