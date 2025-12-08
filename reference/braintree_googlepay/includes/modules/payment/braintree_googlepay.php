<?php
// Include Braintree SDK and the shared common class
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/Braintree.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_common.php');

// Ensure language constants are available when the module is instantiated on the storefront.
if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE')) {
    $languageCode = isset($_SESSION['language']) ? $_SESSION['language'] : 'english';
    $languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $languageCode . '/modules/payment/lang.braintree_googlepay.php';

    if (!file_exists($languageFile)) {
        $languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/payment/lang.braintree_googlepay.php';
    }

    if (file_exists($languageFile)) {
        $define = include $languageFile;
        if (is_array($define)) {
            foreach ($define as $key => $value) {
                if (!defined($key)) {
                    define($key, $value);
                }
            }
        }
    }

    if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE')) {
        define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE', 'Google Pay');
    }
}

if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_DESCRIPTION')) {
    define('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_DESCRIPTION', 'Pay with Google Pay via Braintree.');
}

class braintree_googlepay extends base {
    public $code = 'braintree_googlepay';
    public $title;
    public $description;
    public $enabled;
    public $sort_order;
    public $zone;
    public $order_status;
    public $debug_logging;
    private $braintreeCommon;
    private $cardFundingSource = 'UNKNOWN';
    private $braintreeTableColumnsEnsured = false;
    private $allowedShippingCountryCodes = null;
    private $tokenizationKey = '';

    /**
     * Constructor
     */
    function __construct() {
        global $order;
        $this->order = $order; // assign global order if not already

        $this->code        = 'braintree_googlepay';
        $adminTitle        = defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE') ? trim(MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE) : '';
        $this->title       = $adminTitle !== '' ? $adminTitle : 'Braintree Google Pay';
        $this->description = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_DESCRIPTION;
        $this->sort_order  = defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SORT_ORDER') ? MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SORT_ORDER : null;
        $this->enabled     = defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS == 'True';
        $this->zone        = (int)(defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ZONE') ? MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ZONE : 0);

        // Set order status from configuration if defined, otherwise use the order's current status.
        $this->order_status = (defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS > 0)
            ? MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS
            : (isset($order->info['order_status']) ? $order->info['order_status'] : 0);

        $this->debug_logging = defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_DEBUGGING') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_DEBUGGING != 'Alerts Only';

        // Check if module is installed before running upgrade/license check
        if ($this->check() && $this->enabled && defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) {
            require_once(DIR_FS_ADMIN . '/includes/classes/numinix_plugins.php');
            $nx_plugin = new nxPluginLicCheck();
            $nx_plugin->nxPluginLicense('TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0dPT0dMRV9QQVlfVkVSU0lPTg==:YnJhaW50cmVlX2dvb2dsZXBheQ==:TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0dPT0dMRV9QQVlfU1RBVFVT:MTk1OQ==:QnJhaW50cmVlIEdvb2dsZSBQYXkgZm9yIFplbiBDYXJ0');
        }

        $config = [ 'debug_logging' => $this->debug_logging ];

        if (defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOKENIZATION_KEY')) {
            $this->tokenizationKey = trim(MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOKENIZATION_KEY);
            if ($this->tokenizationKey !== '') {
                $config['tokenization_key'] = $this->tokenizationKey;
            }
        }

        if ($this->enabled) {
            $config = array_merge($config, [
                'environment' => MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SERVER,
                'merchant_id' => MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_KEY,
                'public_key'  => MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PUBLIC_KEY,
                'private_key' => MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRIVATE_KEY
            ]);
        }

        $this->braintreeCommon = new BraintreeCommon($config);

        if ($this->enabled && (!defined('IS_ADMIN_FLAG') || !IS_ADMIN_FLAG)) {
            $this->merchantAccountID = $this->braintreeCommon->get_merchant_account_id($_SESSION['currency']);
        } else {
            $this->merchantAccountID = null;
        }

        // Run update_status if an order object exists
        if (is_object($order)) {
            $this->update_status();
        }
    }

    /**
     * Sets module status based on zone restrictions.
     */
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
            // Additional check: do not allow orders over $10,000 USD.
            $order_amount = $this->calc_order_amount($order->info['total'], 'USD');
            if ($order_amount > 10000 || $order_amount == 0) {
                $this->enabled = false;
            }
        }
    }

    /**
     * Generate the Braintree client token for Google Pay.
     */
    function generate_client_token() {
        return $this->braintreeCommon->generate_client_token($this->merchantAccountID);
    }

    /**
     * Display Google Pay Button on the Checkout Payment Page.
     */
    function selection() {
        global $order, $currencies;

        if (!$this->enabled) return false;

        $googleMerchantId     = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID;
        $googlePayEnvironment = MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT;
        $orderTotalsSelector  = defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOTAL_SELECTOR') ? MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOTAL_SELECTOR : '#orderTotal';

        // Attempt to generate client token with retry logic
        $clientToken = '';
        $delays = [200000, 500000, 1000000]; // microseconds: 200ms, 500ms, 1s

        try {
            $clientToken = (string) $this->generate_client_token();
        } catch (Exception $e) {
            if ($this->debug_logging) {
                error_log('Braintree Google Pay: Initial attempt to generate client token failed - ' . $e->getMessage());
            }

            // Retry with exponential backoff and jitter
            foreach ($delays as $base) {
                $jitter = random_int(- (int)($base * 0.3), (int)($base * 0.3));
                usleep($base + $jitter);
                try {
                    $clientToken = (string) $this->generate_client_token();
                    if ($clientToken !== '') {
                        if ($this->debug_logging) {
                            error_log('Braintree Google Pay: Successfully generated client token after retry');
                        }
                        break;
                    }
                } catch (Exception $retryException) {
                    if ($this->debug_logging) {
                        error_log('Braintree Google Pay: Retry attempt failed - ' . $retryException->getMessage());
                    }
                }
            }

            if ($clientToken === '' && $this->debug_logging) {
                error_log('Braintree Google Pay: All retry attempts exhausted, failed to generate client token');
            }
        }
        $use3DS               = defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS') && MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS === 'True';
        $tokenizationKey      = $this->tokenizationKey;
        $hasTokenizationKey   = ($tokenizationKey !== '');

        if ($clientToken === '' && $hasTokenizationKey && !$use3DS) {
            error_log('Braintree Google Pay: Falling back to the configured tokenization key because a client token was not available.');
        }

        if ($clientToken === '' && ($use3DS || !$hasTokenizationKey)) {
            return array(
                'id'     => $this->code,
                'module' => MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE,
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

        $config = array(
            'clientToken'          => $clientToken,
            'use3DS'               => (bool)$use3DS,
            'storeName'            => STORE_NAME,
            'orderTotal'           => number_format((float)$currencies->value($order->info['total'] ?? 0), 2, '.', ''),
            'currencyCode'         => $order->info['currency'] ?? ($_SESSION['currency'] ?? ''),
            'orderTotalsSelector'  => $orderTotalsSelector,
            'googleMerchantId'     => $googleMerchantId,
            'googlePayEnvironment' => $googlePayEnvironment,
            'tokenizationKey'      => $tokenizationKey,
            'authorizationType'    => $clientToken !== '' ? 'clientToken' : ($hasTokenizationKey ? 'tokenizationKey' : ''),
            'allowedCountryCodes'  => $this->getAllowedShippingCountryCodes(),
            'customerEmail'        => $order->customer['email_address'] ?? '',
            'billing'              => array(
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
            #google-pay-button-container {
                margin: 0.5em 0;
            }

            #google-pay-button-container p.google-pay-unavailable {
                color: #666;
                font-size: 0.9em;
                margin: 0.5em 0;
            }
        </style>
        <div id="google-pay-button-container"></div>
        <script>
        (function () {
            "use strict";

            window.googlePayCardFundingSource = window.googlePayCardFundingSource || "UNKNOWN";
            window.googlePayScriptsLoaded = window.googlePayScriptsLoaded || false;

            const googlePayConfig = <?php echo $configJson; ?>;
            window.googlePayState = window.googlePayState || {};
            if (typeof window.googlePayState.lastAuthorization === "undefined") {
                window.googlePayState.lastAuthorization = null;
            }
            if (typeof window.googlePayState.lastAuthorizationType === "undefined") {
                window.googlePayState.lastAuthorizationType = null;
            }
            if (typeof window.googlePayState.clientInstance === "undefined") {
                window.googlePayState.clientInstance = null;
            }
            if (typeof window.googlePayState.setupPromise === "undefined") {
                window.googlePayState.setupPromise = null;
            }
            if (typeof window.googlePayState.initializationPromise === "undefined") {
                window.googlePayState.initializationPromise = null;
            }
            if (typeof window.googlePayState.retryAttempts === "undefined") {
                window.googlePayState.retryAttempts = 0;
            }
            const googlePayDebugEnabled = true;

            // Detect iOS Chrome (CriOS) - needed for parseResponse handling and sequential loading
            const ua = navigator.userAgent || "";
            const isIOSChrome = /CriOS/.test(ua);
            const MAX_RETRY_ATTEMPTS = isIOSChrome ? 3 : 1; // More retries for iOS Chrome

            function googlePayDebugLog() {
                if (!googlePayDebugEnabled || typeof console === "undefined" || typeof console.log !== "function") {
                    return;
                }
                const args = Array.prototype.slice.call(arguments);
                args.unshift("Google Pay:");
                console.log.apply(console, args);
            }

            function googlePayDebugWarn() {
                if (!googlePayDebugEnabled || typeof console === "undefined") {
                    return;
                }
                const warn = typeof console.warn === "function" ? console.warn : console.log;
                const args = Array.prototype.slice.call(arguments);
                args.unshift("Google Pay:");
                warn.apply(console, args);
            }

            const googlePayWrapperSelectors = [
                ".payment-method-item",
                ".payment-method",
                ".payment-method-option",
                ".payment-option",
                ".payment-option-item",
                ".custom-control",
                ".custom-radio",
                ".braintree_googlepay",
                ".braintree-googlepay"
            ];

            function getGooglePayWrappers(elements) {
                const wrappers = [];
                const targets = Array.isArray(elements) ? elements : [elements];

                targets.forEach(function (el) {
                    if (!el) {
                        return;
                    }

                    googlePayWrapperSelectors.forEach(function (selector) {
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

            function restoreGooglePayWrappers(container) {
                if (!container) {
                    return;
                }

                const wrappers = getGooglePayWrappers([container]);
                wrappers.forEach(function (wrapper) {
                    const state = wrapper.dataset.googlePayWrapperState;
                    if (state === "with-container") {
                        const originalDisplay = wrapper.dataset.googlePayOriginalDisplay;
                        wrapper.style.display = typeof originalDisplay === "string" ? originalDisplay : "";
                        delete wrapper.dataset.googlePayWrapperState;
                        delete wrapper.dataset.googlePayOriginalDisplay;
                    }
                });
            }

            function hideGooglePayOption(options) {
                options = options || {};
                const preserveContainer = options.preserveContainer === true;
                const hideWrapper = options.hideWrapper !== false;

                const radio = document.querySelector("input[type='radio'][name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                const lbl = document.querySelector("label[for='pmt-braintree_googlepay']");
                const container = document.getElementById("google-pay-button-container");
                if (container && !preserveContainer) {
                    container.style.display = "none";
                }

                if (!hideWrapper) {
                    return;
                }

                const wrappers = getGooglePayWrappers([radio, lbl, container]);
                wrappers.forEach(function (wrapper) {
                    if (!wrapper) {
                        return;
                    }

                    const containsContainer = container && wrapper.contains(container);
                    const shouldHide = !containsContainer || !preserveContainer;

                    if (!shouldHide) {
                        return;
                    }

                    if (typeof wrapper.dataset.googlePayOriginalDisplay === "undefined") {
                        wrapper.dataset.googlePayOriginalDisplay = wrapper.style.display || "";
                    }
                    wrapper.style.display = "none";
                    wrapper.dataset.googlePayWrapperState = containsContainer ? "with-container" : "without-container";
                });
            }

            function loadGooglePayScripts() {
                if (window.googlePayScriptsLoaded) {
                    googlePayDebugLog("Required scripts already loaded");
                    return Promise.resolve();
                }

                const scripts = [
                    "https://pay.google.com/gp/p/js/pay.js",
                    "https://js.braintreegateway.com/web/3.133.0/js/client.min.js",
                    "https://js.braintreegateway.com/web/3.133.0/js/google-payment.min.js"
                ];

                if (googlePayConfig.use3DS) {
                    scripts.push("https://js.braintreegateway.com/web/3.133.0/js/three-d-secure.min.js");
                } else {
                    googlePayDebugLog("3DS disabled via module settings; skipping 3DS resources");
                    delete window.threeDS;
                }

                const loadScript = function (src) {
                    return new Promise(function (resolve, reject) {
                        const selector = 'script[src="' + src.replace(/"/g, '\\"') + '"]';
                        const existing = document.querySelector(selector);
                        if (existing) {
                            if (existing.dataset.loaded === 'true') {
                                googlePayDebugLog("Script already loaded", src);
                                resolve();
                                return;
                            }
                            // Script exists but may already be loaded (e.g., from cache on page refresh)
                            // Check if it's already loaded or still loading
                            if (existing.readyState === 'complete' || existing.readyState === 'loaded') {
                                existing.dataset.loaded = 'true';
                                googlePayDebugLog("Script already loaded (from cache)", src);
                                resolve();
                                return;
                            }
                            existing.addEventListener('load', function () {
                                existing.dataset.loaded = 'true';
                                googlePayDebugLog("Script loaded", src);
                                resolve();
                            });
                            existing.addEventListener('error', function () {
                                googlePayDebugWarn("Failed to load script", src);
                                reject(new Error("Failed to load script: " + src));
                            });
                            return;
                        }

                        const script = document.createElement("script");
                        script.src = src;
                        script.async = true;
                        script.dataset.loaded = 'false';
                        script.addEventListener('load', function () {
                            script.dataset.loaded = 'true';
                            googlePayDebugLog("Loaded script", src);
                            resolve();
                        });
                        script.addEventListener('error', function () {
                            googlePayDebugWarn("Failed to load script", src);
                            reject(new Error("Failed to load script: " + src));
                        });
                        document.head.appendChild(script);
                    });
                };

                googlePayDebugLog("Loading Google Pay resources", scripts);
                googlePayDebugLog("Browser detection - iOS Chrome:", isIOSChrome);

                // iOS Chrome requires sequential loading to avoid race conditions and initialization issues
                // All other browsers work fine with parallel loading (faster)
                if (isIOSChrome) {
                    googlePayDebugLog("Using sequential loading for iOS Chrome");
                    return scripts.reduce(function (promise, src) {
                        return promise.then(function () {
                            return loadScript(src);
                        });
                    }, Promise.resolve()).then(function () {
                        window.googlePayScriptsLoaded = true;
                    });
                } else {
                    googlePayDebugLog("Using parallel loading for non-iOS Chrome browsers");
                    return Promise.all(scripts.map(function (src) {
                        return loadScript(src);
                    })).then(function () {
                        window.googlePayScriptsLoaded = true;
                    });
                }
            }

            function setup3DS(clientInstance, authorizationDetails) {
                if (!googlePayConfig.use3DS) {
                    googlePayDebugLog("3DS disabled; skipping setup");
                    delete window.threeDS;
                    return Promise.resolve();
                }

                if (!authorizationDetails || authorizationDetails.type !== "clientToken") {
                    googlePayDebugWarn("3DS requires a client token; skipping setup because a tokenization key is in use.");
                    delete window.threeDS;
                    return Promise.resolve();
                }

                if (!braintree.threeDSecure || typeof braintree.threeDSecure.create !== "function") {
                    googlePayDebugWarn("3DS library unavailable; skipping setup");
                    delete window.threeDS;
                    return Promise.resolve();
                }

                if (window.threeDS && typeof window.threeDS.teardown === "function") {
                    googlePayDebugLog("Tearing down previous 3DS instance");
                    window.threeDS.teardown();
                    delete window.threeDS;
                }

                return braintree.threeDSecure.create({
                    client: clientInstance,
                    version: 2
                }).then(function (threeDSInstance) {
                    window.threeDS = threeDSInstance;
                    googlePayDebugLog("3DS setup complete");
                }).catch(function (err) {
                    googlePayDebugWarn("Failed to initialize 3DS", err);
                    delete window.threeDS;
                });
            }

            function ensureHiddenField(form, name, value) {
                let field = form.querySelector("input[name='" + name + "']");
                if (!field) {
                    field = document.createElement("input");
                    field.type = "hidden";
                    field.name = name;
                    form.appendChild(field);
                }
                field.value = value;
            }

            function renderUnavailableMessage(message) {
                const container = document.getElementById("google-pay-button-container");
                if (!container) {
                    return;
                }
                container.innerHTML = "";
                container.style.display = "block";
                restoreGooglePayWrappers(container);
                const msg = document.createElement("p");
                msg.className = "google-pay-unavailable";
                msg.textContent = message;
                container.appendChild(msg);
            }

            function getAuthorizationDetails() {
                const currentConfig = googlePayConfig || {};
                const configType = currentConfig.authorizationType || "";
                const hasClientToken = typeof currentConfig.clientToken === "string" && currentConfig.clientToken !== "";
                const hasTokenizationKey = typeof currentConfig.tokenizationKey === "string" && currentConfig.tokenizationKey !== "";

                let type = configType;
                if (!type) {
                    if (hasClientToken) {
                        type = "clientToken";
                    } else if (hasTokenizationKey) {
                        type = "tokenizationKey";
                    } else {
                        type = null;
                    }
                }

                let value = "";
                if (type === "clientToken" && hasClientToken) {
                    value = currentConfig.clientToken;
                } else if (type === "tokenizationKey" && hasTokenizationKey) {
                    value = currentConfig.tokenizationKey;
                } else if (!type && hasClientToken) {
                    value = currentConfig.clientToken;
                    type = "clientToken";
                } else if (!type && hasTokenizationKey) {
                    value = currentConfig.tokenizationKey;
                    type = "tokenizationKey";
                }

                return {
                    value: value || "",
                    type: type || null
                };
            }

            function initializeGooglePay() {
                googlePayDebugLog("initializeGooglePay invoked");
                const container = document.getElementById("google-pay-button-container");
                const state = window.googlePayState;

                if (state && state.initializationPromise) {
                    googlePayDebugLog("Initialization already in progress; reusing existing promise");
                    return state.initializationPromise;
                }

                if (!container) {
                    googlePayDebugWarn("Google Pay button container not found");
                    hideGooglePayOption();
                    return Promise.resolve();
                }

                restoreGooglePayWrappers(container);

                if (container.dataset.googlePayButtonReady === "true") {
                    googlePayDebugLog("Google Pay button already rendered; skipping");
                    return Promise.resolve();
                }

                container.innerHTML = "";
                container.style.display = "";
                delete container.dataset.googlePayButtonReady;

                if (typeof braintree === "undefined" || !braintree.client) {
                    googlePayDebugWarn("Braintree client not available yet");
                    return Promise.resolve();
                }

                const authorizationDetails = getAuthorizationDetails();
                const authorization = authorizationDetails.value;
                const authorizationType = authorizationDetails.type;

                if (!authorization) {
                    googlePayDebugLog("Authorization unavailable; delaying initialization");
                    return Promise.resolve();
                }

                const needsNewClient = state.lastAuthorization !== authorization
                    || state.lastAuthorizationType !== authorizationType
                    || !state.clientInstance;

                let clientPromise;

                if (needsNewClient) {
                    googlePayDebugLog("Creating new Braintree client");
                    clientPromise = braintree.client.create({
                        authorization: authorization
                    }).then(function (clientInstance) {
                        state.lastAuthorization = authorization;
                        state.lastAuthorizationType = authorizationType;
                        state.clientInstance = clientInstance;
                        return setup3DS(clientInstance, authorizationDetails).then(function () {
                            return clientInstance;
                        });
                    });
                } else {
                    googlePayDebugLog("Reusing cached Braintree client");
                    clientPromise = Promise.resolve(state.clientInstance).then(function (clientInstance) {
                        if (googlePayConfig.use3DS) {
                            if (authorizationType === "clientToken" && (!window.threeDS || window.threeDS._destroyed)) {
                                return setup3DS(clientInstance, authorizationDetails).then(function () {
                                    return clientInstance;
                                });
                            }

                            if (authorizationType !== "clientToken" && window.threeDS && typeof window.threeDS.teardown === "function") {
                                window.threeDS.teardown();
                                delete window.threeDS;
                            }
                        }
                        return clientInstance;
                    });
                }

                var initializationPromise = clientPromise.then(function (clientInstance) {
                    if (!needsNewClient && window.googlePaymentInstance) {
                        googlePayDebugLog("Reusing cached Google Pay instance");
                        return window.googlePaymentInstance;
                    }

                    googlePayDebugLog("Creating Google Pay instance");
                    return braintree.googlePayment.create({
                        client: clientInstance,
                        googlePayVersion: 2,
                        googleMerchantId: googlePayConfig.googleMerchantId || undefined
                    }).then(function (googlePaymentInstance) {
                        window.googlePaymentInstance = googlePaymentInstance;
                        return googlePaymentInstance;
                    });
                }).then(function (googlePaymentInstance) {
                    if (!googlePaymentInstance) {
                        googlePayDebugWarn("Google Pay instance not available");
                        renderUnavailableMessage("Google Pay is temporarily unavailable. Please choose another payment method.");
                        return;
                    }

                    window.googlePaymentInstance = googlePaymentInstance;

                    const paymentsClient = new google.payments.api.PaymentsClient({
                        environment: googlePayConfig.googlePayEnvironment
                    });

                    const button = paymentsClient.createButton({
                        onClick: function () {
                            const googlePayRadio = document.querySelector("input[type='radio'][name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                            if (googlePayRadio) {
                                googlePayRadio.checked = true;
                                googlePayRadio.dispatchEvent(new Event("change"));
                            }

                            const paymentDataRequest = googlePaymentInstance.createPaymentDataRequest({
                                transactionInfo: {
                                    currencyCode: googlePayConfig.currencyCode,
                                    totalPriceStatus: "FINAL",
                                    totalPrice: window.googlePayDynamicTotal || googlePayConfig.orderTotal
                                }
                            });

                            if (paymentDataRequest.allowedPaymentMethods && paymentDataRequest.allowedPaymentMethods[0] && paymentDataRequest.allowedPaymentMethods[0].parameters) {
                                paymentDataRequest.allowedPaymentMethods[0].parameters.billingAddressRequired = true;
                                paymentDataRequest.allowedPaymentMethods[0].parameters.billingAddressParameters = {
                                    format: "FULL",
                                    phoneNumberRequired: true
                                };
                            }

                            if (Array.isArray(googlePayConfig.allowedCountryCodes) && googlePayConfig.allowedCountryCodes.length > 0) {
                                paymentDataRequest.shippingAddressRequired = true;
                                paymentDataRequest.shippingOptionRequired = false;
                                paymentDataRequest.shippingAddressParameters = Object.assign(
                                    { phoneNumberRequired: true },
                                    paymentDataRequest.shippingAddressParameters || {},
                                    { allowedCountryCodes: googlePayConfig.allowedCountryCodes }
                                );
                            } else {
                                paymentDataRequest.shippingAddressRequired = false;
                                paymentDataRequest.shippingOptionRequired = false;
                            }

                            paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
                                let fundingSource = "UNKNOWN";
                                if (paymentData && paymentData.paymentMethodData && paymentData.paymentMethodData.info && paymentData.paymentMethodData.info.cardInfo && paymentData.paymentMethodData.info.cardInfo.cardFundingSource) {
                                    fundingSource = paymentData.paymentMethodData.info.cardInfo.cardFundingSource;
                                }
                                window.googlePayCardFundingSource = fundingSource || "UNKNOWN";
                                
                                // iOS Chrome requires special handling for parseResponse
                                // The paymentData object needs to be passed directly, but we need to ensure
                                // it's properly cloned to avoid reference issues in the postMessage communication
                                if (isIOSChrome) {
                                    googlePayDebugLog("iOS Chrome detected - using JSON round-trip for parseResponse");
                                    try {
                                        // Deep clone the paymentData to avoid postMessage serialization issues
                                        const clonedPaymentData = JSON.parse(JSON.stringify(paymentData));
                                        return googlePaymentInstance.parseResponse(clonedPaymentData);
                                    } catch (err) {
                                        googlePayDebugWarn("Failed to clone paymentData, falling back to direct call", err);
                                        return googlePaymentInstance.parseResponse(paymentData);
                                    }
                                }
                                
                                return googlePaymentInstance.parseResponse(paymentData);
                            }).then(function (result) {
                                if (!result || !result.nonce) {
                                    googlePayDebugWarn("Tokenization result missing nonce");
                                    return;
                                }

                                const form = document.getElementById("checkout_payment")
                                    || document.querySelector("form[name='checkout_payment']")
                                    || (document.forms && document.forms.namedItem && document.forms.namedItem("checkout_payment"));

                                if (!form) {
                                    googlePayDebugWarn("Checkout form not found");
                                    return;
                                }

                                const submitNonce = function (nonce) {
                                    ensureHiddenField(form, "payment_method_nonce", nonce);
                                    ensureHiddenField(form, "google_pay_card_funding_source", window.googlePayCardFundingSource || "UNKNOWN");

                                    const radio = document.querySelector("input[type='radio'][name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                                    if (radio) {
                                        radio.checked = true;
                                        radio.dispatchEvent(new Event("change"));
                                    }

                                    form.submit();
                                };

                                if (googlePayConfig.use3DS && window.threeDS && typeof window.threeDS.verifyCard === "function" && !window.threeDS._destroyed) {
                                    googlePayDebugLog("Initiating 3DS verification");
                                    if (!result.details || !result.details.bin) {
                                        googlePayDebugWarn("3DS skipped: Missing BIN data");
                                        submitNonce(result.nonce);
                                        return;
                                    }

                                    window.threeDS.verifyCard({
                                        amount: googlePayConfig.orderTotal,
                                        nonce: result.nonce,
                                        bin: result.details.bin,
                                        email: googlePayConfig.customerEmail,
                                        billingAddress: googlePayConfig.billing,
                                        onLookupComplete: function (data, next) {
                                            next();
                                        }
                                    }).then(function (verification) {
                                        googlePayDebugLog("3DS verification complete", verification);
                                        if (verification && verification.nonce) {
                                            submitNonce(verification.nonce);
                                        } else {
                                            alert("3D Secure verification did not return a valid nonce.");
                                        }
                                    }).catch(function (err) {
                                        googlePayDebugWarn("3DS verification failed", err);
                                        alert("3D Secure authentication failed. Please try again.");
                                    });
                                } else {
                                    googlePayDebugLog("Skipping 3DS verification");
                                    submitNonce(result.nonce);
                                }
                            }).catch(function (err) {
                                googlePayDebugWarn("Error loading payment data", err);
                            });
                        }
                    });

                    button.id = "google-pay-button";
                    button.className += (button.className ? " " : "") + "google-pay-button";

                    window.googlePayButton = button;
                    window.triggerGooglePayPayment = function () {
                        button.click();
                    };

                    container.appendChild(button);
                    const googlePayRadio = document.querySelector("input[type='radio'][name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                    if (googlePayRadio) {
                        googlePayRadio.style.display = "";
                    }
                    const googlePayLabel = document.querySelector("label[for='pmt-braintree_googlepay']");
                    if (googlePayLabel) {
                        googlePayLabel.style.display = "";
                    }
                    restoreGooglePayWrappers(container);
                    container.dataset.googlePayButtonReady = "true";
                    googlePayDebugLog("Google Pay button rendered");

                    const radio = document.querySelector("input[type='radio'][name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                    if (radio && !radio.dataset.googlePayBound) {
                        radio.addEventListener("click", function () {
                            button.click();
                        });
                        radio.dataset.googlePayBound = "true";
                    }

                    initSubmitButtonToggle();
                    return googlePaymentInstance;
                }).catch(function (error) {
                    if (needsNewClient) {
                        state.clientInstance = null;
                        state.lastAuthorization = null;
                    }
                    googlePayDebugWarn("Error initializing Google Pay", error);
                    hideGooglePayOption();
                    renderUnavailableMessage("Google Pay is temporarily unavailable. Please choose another payment method.");
                });

                state.initializationPromise = initializationPromise;

                initializationPromise.then(function () {
                    state.initializationPromise = null;
                }, function () {
                    state.initializationPromise = null;
                });

                return initializationPromise;
            }

            function initSubmitButtonToggle() {
                const form = document.querySelector("form[name='checkout_payment']");
                if (!form) {
                    return;
                }

                const submitBtn = form.querySelector("button[type='submit']");
                if (!submitBtn) {
                    return;
                }

                const googlePayRadio = form.querySelector("input[type='radio'][name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                const paymentRadios = form.querySelectorAll("input[type='radio'][name='payment']");

                if (!googlePayRadio || paymentRadios.length === 0) {
                    return;
                }

                function toggle() {
                    if (googlePayRadio.checked) {
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

            function runWhenDocumentReady(callback) {
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", callback);
                } else {
                    callback();
                }
            }

            function prepareGooglePaySetup() {
                googlePayDebugLog("Preparing Google Pay setup (attempt " + (window.googlePayState.retryAttempts + 1) + " of " + MAX_RETRY_ATTEMPTS + ")");
                const state = window.googlePayState;

                if (state && state.setupPromise) {
                    googlePayDebugLog("Setup already in progress; reusing existing promise");
                    return state.setupPromise;
                }

                if (state && state.initializationPromise) {
                    googlePayDebugLog("Initialization already in progress; waiting for completion");
                    return state.initializationPromise;
                }

                const googlePayRadio = document.querySelector("input[name='payment'][value='braintree_googlepay'], #pmt-braintree_googlepay");
                if (googlePayRadio) {
                    googlePayRadio.style.display = "";
                    const googlePayLabel = document.querySelector("label[for='pmt-braintree_googlepay']");
                    if (googlePayLabel) {
                        googlePayLabel.style.display = "";
                    }
                    googlePayRadio.addEventListener("click", function () {
                        const btn = document.querySelector("#google-pay-button-container button");
                        if (btn) {
                            btn.click();
                        }
                    });
                }

                initSubmitButtonToggle();

                var setupPromise = loadGooglePayScripts().then(function () {
                    googlePayDebugLog("Scripts ready; initializing Google Pay");
                    state.retryAttempts = 0; // Reset on success
                    return initializeGooglePay();
                }).catch(function (err) {
                    googlePayDebugWarn("Failed to load Google Pay scripts", err);
                    
                    // Retry logic for iOS Chrome
                    if (state.retryAttempts < MAX_RETRY_ATTEMPTS - 1) {
                        state.retryAttempts++;
                        const delay = Math.min(1000 * Math.pow(2, state.retryAttempts - 1), 5000);
                        googlePayDebugLog("Retrying in " + delay + "ms (attempt " + state.retryAttempts + " of " + MAX_RETRY_ATTEMPTS + ")");
                        
                        // Clear the setup promise so retry can proceed
                        state.setupPromise = null;
                        
                        return new Promise(function (resolve) {
                            setTimeout(function () {
                                resolve(prepareGooglePaySetup());
                            }, delay);
                        });
                    } else {
                        googlePayDebugWarn("Max retry attempts reached. Hiding Google Pay option.");
                        hideGooglePayOption();
                        renderUnavailableMessage("Google Pay is temporarily unavailable. Please choose another payment method.");
                    }
                });

                if (state) {
                    state.setupPromise = setupPromise;

                    setupPromise.then(function () {
                        state.setupPromise = null;
                    }, function () {
                        state.setupPromise = null;
                    });
                }

                return setupPromise;
            }

            runWhenDocumentReady(prepareGooglePaySetup);

            if (window.googlePayReloadHandler) {
                document.removeEventListener("onePageCheckoutReloaded", window.googlePayReloadHandler);
            }

            window.googlePayReloadHandler = function () {
                googlePayDebugLog("onePageCheckoutReloaded detected; reinitializing");
                prepareGooglePaySetup();
            };

            document.addEventListener("onePageCheckoutReloaded", window.googlePayReloadHandler);
        })();
        </script>
        <?php
        $output = ob_get_clean();

        return array(
            'id'     => $this->code,
            'module' => MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TEXT_ADMIN_TITLE,
            'fields' => array(array('title' => '', 'field' => $output))
        );
    }

    function pre_confirmation_check() {
        global $messageStack;
        if (empty($_POST['payment_method_nonce'])) {
            $messageStack->add_session('checkout_payment', 'Must authorize Google Pay first', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
        $this->nonce = $_SESSION['google_pay_nonce'] = $_POST['payment_method_nonce'];
        $fundingSource = isset($_POST['google_pay_card_funding_source']) ? $_POST['google_pay_card_funding_source'] : ($_SESSION['google_pay_card_funding_source'] ?? null);
        $this->storeCardFundingSource($fundingSource);
    }

    function get_error() {
        return false;
    }

    function confirmation() {
        return false;
    }

    /**
     * Store the Google Pay token (nonce) as a hidden field in the checkout form.
     */
    function process_button() {
        $process_button_string = "";
        $hasNonce = false;
        if (!empty($_POST['payment_method_nonce'])) {
            $process_button_string .= "\n" . zen_draw_hidden_field('payment_method_nonce', $_POST['payment_method_nonce']);
            $hasNonce = true;
        } elseif (!empty($_SESSION['google_pay_nonce'])) {
            $process_button_string .= "\n" . zen_draw_hidden_field('payment_method_nonce', $_SESSION['google_pay_nonce']);
            $hasNonce = true;
        }
        $fundingSource = null;
        if (isset($_POST['google_pay_card_funding_source'])) {
            $fundingSource = $this->storeCardFundingSource($_POST['google_pay_card_funding_source']);
        } elseif ($hasNonce) {
            $fundingSource = $this->getStoredCardFundingSource();
        }
        if ($hasNonce && !empty($fundingSource)) {
            $process_button_string .= "\n" . zen_draw_hidden_field('google_pay_card_funding_source', $fundingSource);
        }
        return $process_button_string;
    }

    function process_button_ajax() {
        global $order;
        $processButton = array(
            'ccFields'    => array(),
            'extraFields' => array(zen_session_name() => zen_session_id())
        );
        if (!empty($_POST['payment_method_nonce'])) {
            $processButton['ccFields']['bt_nonce']         = $_POST['payment_method_nonce'];
            $processButton['ccFields']['bt_payment_type']    = 'google_pay';
            $processButton['ccFields']['bt_currency_code']   = $order->info['currency'];
            $fundingSource = isset($_POST['google_pay_card_funding_source']) ? $this->storeCardFundingSource($_POST['google_pay_card_funding_source']) : $this->getStoredCardFundingSource();
            if (!empty($fundingSource)) {
                $processButton['ccFields']['bt_card_funding_source'] = $fundingSource;
            }
        } elseif (isset($_POST['google_pay_card_funding_source'])) {
            $this->storeCardFundingSource($_POST['google_pay_card_funding_source']);
        }
        return $processButton;
    }

    /**
     * Validate the Google Pay response and complete the payment.
     */
    function before_process() {
        return $this->braintreeCommon->before_process_common($this->merchantAccountID, array(), (MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SETTLEMENT == 'true'));
    }

    /**
     * After the transaction is processed, update order status history and store transaction details.
     * Uses the Pending status if the payment was not captured.
     */
    function after_process() {
        global $insert_id, $db, $order;

        // Retrieve transaction details from session
        $txnId = $_SESSION['braintree_transaction_id'] ?? '';
        $paymentStatus = $_SESSION['braintree_payment_status'] ?? 'Pending';
        $currency = $_SESSION['braintree_currency'] ?? '';
        $amount = $_SESSION['braintree_amount'] ?? 0;
        $fundingSource = $this->getStoredCardFundingSource();

        // Determine order status based on transaction settlement
        $orderStatus = (MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SETTLEMENT == 'true')
            ? MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS
            : MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID;

        if ($txnId) {
            // Update order status in Zen Cart
            $db->Execute("UPDATE " . TABLE_ORDERS . "
                          SET orders_status = " . (int)$orderStatus . "
                          WHERE orders_id = " . (int)$insert_id);

            // Insert into order status history
            $sql_data_array = [
                'orders_id'         => (int)$insert_id,
                'orders_status_id'  => (int)$orderStatus,
                'date_added'        => 'now()',
                'comments'          => "Braintree Google Pay Transaction ID: " . $txnId,
                'customer_notified' => 1
            ];
            zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

            // Insert transaction details into Braintree table
            $google_pay_order = [
                'order_id'            => (int)$insert_id,
                'txn_id'              => $txnId,
                'txn_type'            => 'sale',
                'module_name'         => 'braintree_googlepay',
                'payment_type'        => 'Google Pay',
                'payment_status'      => $paymentStatus,
                'pending_reason'      => '',
                'first_name'          => $order->billing['firstname'] ?? '',
                'last_name'           => $order->billing['lastname'] ?? '',
                'payer_business_name' => $order->billing['company'] ?? '',
                'address_name'        => ($order->billing['firstname'] ?? '') . ' ' . ($order->billing['lastname'] ?? ''),
                'address_street'      => $order->billing['street_address'] ?? '',
                'address_city'        => $order->billing['city'] ?? '',
                'address_state'       => $order->billing['state'] ?? '',
                'address_zip'         => $order->billing['postcode'] ?? '',
                'address_country'     => $order->billing['country']['title'] ?? ($order->billing['country'] ?? ''),
                'payer_email'         => $order->customer['email_address'] ?? ($_SESSION['google_pay_payer_email'] ?? ''),
                'payment_date'        => date('Y-m-d'),
                'settle_amount'       => (float)$amount,
                'settle_currency'     => $currency,
                'exchange_rate'       => 0,
                'date_added'          => date('Y-m-d'),
                'module_mode'         => 'google_pay',
                'card_funding_source' => $fundingSource
            ];
            static $columnsEnsured = false;
            if (!$columnsEnsured) {
                $columnsEnsured = true;
                $this->ensureGooglePayBraintreeColumn('card_funding_source', "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN card_funding_source VARCHAR(32) NULL");
                $this->ensureGooglePayBraintreeColumn('pending_reason', "ALTER TABLE " . TABLE_BRAINTREE . " ADD COLUMN pending_reason VARCHAR(255) NOT NULL DEFAULT ''");
            }
            zen_db_perform(TABLE_BRAINTREE, $google_pay_order);
        } else {
            error_log("Braintree Google Pay Error: Missing transaction ID for order ID: $insert_id.");
        }

        // Cleanup session variables
        unset($_SESSION['braintree_transaction_id'], $_SESSION['braintree_payment_status'], $_SESSION['braintree_currency'], $_SESSION['braintree_amount'], $_SESSION['google_pay_card_funding_source']);
    }

    private function normalizeFundingSource($source) {
        if (is_array($source)) {
            $source = '';
        }
        if (function_exists('zen_db_prepare_input')) {
            $source = zen_db_prepare_input($source);
        }
        if (!is_string($source)) {
            $source = '';
        }
        $source = trim($source);
        return ($source === '') ? 'UNKNOWN' : $source;
    }

    private function storeCardFundingSource($source) {
        $normalized = $this->normalizeFundingSource($source);
        $this->cardFundingSource = $normalized;
        $_SESSION['google_pay_card_funding_source'] = $normalized;
        return $normalized;
    }

    private function getStoredCardFundingSource() {
        $source = $this->cardFundingSource ?? null;
        if ($source === null || $source === '') {
            $source = $_SESSION['google_pay_card_funding_source'] ?? null;
        }
        return $this->storeCardFundingSource($source);
    }


    private function getAllowedShippingCountryCodes() {
        if (is_array($this->allowedShippingCountryCodes)) {
            return $this->allowedShippingCountryCodes;
        }

        $this->allowedShippingCountryCodes = [];

        if (!defined('TABLE_COUNTRIES')) {
            return $this->allowedShippingCountryCodes;
        }

        global $db;

        if (!isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
            return $this->allowedShippingCountryCodes;
        }

        $countries = [];

        try {
            if ((int)$this->zone > 0 && defined('TABLE_ZONES_TO_GEO_ZONES')) {
                $countryLookup = $db->Execute("SELECT DISTINCT zone_country_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = " . (int)$this->zone);
                if (is_object($countryLookup)) {
                    while (!$countryLookup->EOF) {
                        $countryId = (int)$countryLookup->fields['zone_country_id'];
                        $code = $this->lookupCountryIsoCode($countryId);
                        if ($code !== null) {
                            $countries[$code] = true;
                        }
                        if (method_exists($countryLookup, 'MoveNext')) {
                            $countryLookup->MoveNext();
                        } else {
                            break;
                        }
                    }
                }
            } else {
                $allCountries = $db->Execute("SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " ORDER BY countries_iso_code_2");
                if (is_object($allCountries)) {
                    while (!$allCountries->EOF) {
                        $code = strtoupper(trim($allCountries->fields['countries_iso_code_2'] ?? ''));
                        if ($code !== '') {
                            $countries[$code] = true;
                        }
                        if (method_exists($allCountries, 'MoveNext')) {
                            $allCountries->MoveNext();
                        } else {
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->debug_logging && function_exists('error_log')) {
                error_log('Braintree Google Pay: Unable to determine allowed shipping countries - ' . $e->getMessage());
            }
        }

        $this->allowedShippingCountryCodes = array_values(array_keys($countries));

        return $this->allowedShippingCountryCodes;
    }

    private function lookupCountryIsoCode($countryId) {
        global $db;

        if (!defined('TABLE_COUNTRIES') || $countryId <= 0 || !isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
            return null;
        }

        try {
            $country = $db->Execute("SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)$countryId . " LIMIT 1");
            if (is_object($country) && isset($country->fields['countries_iso_code_2'])) {
                $code = strtoupper(trim($country->fields['countries_iso_code_2']));
                return ($code === '') ? null : $code;
            }
        } catch (Exception $e) {
            if ($this->debug_logging && function_exists('error_log')) {
                error_log('Braintree Google Pay: Unable to lookup country code for ID ' . (int)$countryId . ' - ' . $e->getMessage());
            }
        }

        return null;
    }

    private function ensureGooglePayBraintreeColumn($columnName, $alterSql) {
        global $db;

        if (!defined('TABLE_BRAINTREE') || !isset($db) || !is_object($db) || !method_exists($db, 'Execute')) {
            return;
        }

        try {
            $column = $db->Execute("SHOW COLUMNS FROM " . TABLE_BRAINTREE . " LIKE '" . $columnName . "'");
            $exists = false;
            if (is_object($column)) {
                if (method_exists($column, 'RecordCount')) {
                    $exists = ($column->RecordCount() > 0);
                } elseif (isset($column->fields) && is_array($column->fields) && count($column->fields) > 0) {
                    $exists = true;
                }
            }
            if (!$exists) {
                $db->Execute($alterSql);
            }
        } catch (Exception $e) {
            if ($this->debug_logging && function_exists('error_log')) {
                error_log('Braintree Google Pay: Unable to ensure ' . $columnName . ' column - ' . $e->getMessage());
            }
        }
    }

    // The following functions now simply delegate to the common class

    function getTransactionId($orderId) {
        return $this->braintreeCommon->getTransactionId($orderId);
    }

    function _GetTransactionDetails($oID) {
        return $this->braintreeCommon->_GetTransactionDetails($oID);
    }

    function _doRefund($oID, $amount = 'Full', $note = '') {
        global $messageStack;
        try {
            // Call the refund function from the common class
            $success = $this->braintreeCommon->_doRefund($oID, $amount, $note);

            if (!$success) {
                throw new Exception("Refund processing failed.");
            }

            $messageStack->add_session("Refund processed successfully for Order #{$oID}", 'success');
        } catch (Exception $e) {
            $messageStack->add_session("Refund Error: " . $e->getMessage(), 'error');
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
        return $this->braintreeCommon->capturePayment(
            $order_id,
            MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS, // Use the module-specific paid status configuration
            $this->code
        );
    }

    function check() {
        global $db;
        if (!isset($this->_check)) {

            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * Upgrade the module by adding missing configuration options
     */
    function install() {
        require_once(DIR_FS_ADMIN . '/includes/classes/numinix_plugins.php');

        $nx_plugin = new nxPluginLicCheck();
        $nx_plugin->nxPluginLicense('TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0dPT0dMRV9QQVlfVkVSU0lPTg==:YnJhaW50cmVlX2dvb2dsZXBheQ==:TU9EVUxFX1BBWU1FTlRfQlJBSU5UUkVFX0dPT0dMRV9QQVlfU1RBVFVT:MTk1OQ==:QnJhaW50cmVlIEdvb2dsZSBQYXkgZm9yIFplbiBDYXJ0');

        // Ensure the Braintree table exists via the common class.
        $this->braintreeCommon->create_braintree_table();
    }


    function remove() {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
        return array(
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_VERSION',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SERVER',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PUBLIC_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRIVATE_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOKENIZATION_KEY',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_MERCHANT_ID',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ENVIRONMENT',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SETTLEMENT',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_USE_3DS',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ORDER_STATUS',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PENDING_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_PRODUCT_PAGE',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SHOPPING_CART',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_TOTAL_SELECTOR',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_DEBUGGING',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_ZONE',
            'MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_SORT_ORDER'
        );
    }

    public function javascript_validation() {
        return false;
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

    function admin_notification($zf_order_id) {
        if (!defined('MODULE_PAYMENT_BRAINTREE_GOOGLE_PAY_STATUS')) return '';
        global $db;
        $module = $this->code;
        $output = '';
        $response = $this->_GetTransactionDetails($zf_order_id);
        if (file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_admin_notification.php')) {
            include_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_admin_notification.php');
        }
        return $output;
    }
}