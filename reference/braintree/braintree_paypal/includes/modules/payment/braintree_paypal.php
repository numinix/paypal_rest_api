<?php
// Include Braintree SDK and the shared common class
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/Braintree.php';
require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_common.php';

/**
 * Class braintree_paypal
 *
 * A payment module for processing PayPal payments via Braintree.
 */
class braintree_paypal {
    public $code = 'braintree_paypal';
    public $title;
    public $description;
    public $enabled;
    public $sort_order;
    public $zone;
    public $order_status;
    public $debug_logging;
    private $braintreeCommon;
    private $tokenizationKey = '';

    /**
     * Constructor
     *
     * Initializes module settings and instantiates the shared BraintreeCommon class.
     */
    function __construct() {
        global $order;
        $this->order = $order; // assign global order if not already

        $this->code = 'braintree_paypal';
        $this->title = MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_TITLE;
        $this->description = MODULE_PAYMENT_BRAINTREE_PAYPAL_TEXT_ADMIN_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_SORT_ORDER') ? MODULE_PAYMENT_BRAINTREE_PAYPAL_SORT_ORDER : null;
        $this->enabled = defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS') && MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS == 'True';
        $this->zone = (int)(defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_ZONE') ? MODULE_PAYMENT_BRAINTREE_PAYPAL_ZONE : 0);

        $this->order_status = (defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS') && MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS > 0)
            ? MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS
            : (isset($order->info['order_status']) ? $order->info['order_status'] : 0);

        $this->debug_logging = defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING') && MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING != 'Alerts Only';

        $config = [ 'debug_logging' => $this->debug_logging ];

        if (defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_TOKENIZATION_KEY')) {
            $this->tokenizationKey = trim(MODULE_PAYMENT_BRAINTREE_PAYPAL_TOKENIZATION_KEY);
            if ($this->tokenizationKey !== '') {
                $config['tokenization_key'] = $this->tokenizationKey;
            }
        }

        if ($this->enabled) {
            $config = array_merge($config, [
                'environment' => MODULE_PAYMENT_BRAINTREE_PAYPAL_SERVER,
                'merchant_id' => MODULE_PAYMENT_BRAINTREE_PAYPAL_MERCHANT_KEY,
                'public_key'  => MODULE_PAYMENT_BRAINTREE_PAYPAL_PUBLIC_KEY,
                'private_key' => MODULE_PAYMENT_BRAINTREE_PAYPAL_PRIVATE_KEY
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
     * update_status
     *
     * Checks if the module should be enabled for the customer's billing zone.
     */
    function update_status() {
        global $order, $db, $currencies;
        if ($this->enabled && (int) $this->zone > 0) {
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
            $order_amount = $currencies->value($order->info['total'], 'USD');
            if ($order_amount > 10000 || $order->info['total'] == 0) {
                $this->enabled = false;
            }
        }
    }

    function generate_client_token() {
        $clientToken = $this->braintreeCommon->generate_client_token($this->merchantAccountID);
        
        // If initial attempt fails, retry with exponential backoff and jitter
        if (!$clientToken) {
            $delays = [200000, 500000, 1000000]; // microseconds: 200ms, 500ms, 1s
            foreach ($delays as $base) {
                $jitter = random_int(- (int)($base * 0.3), (int)($base * 0.3));
                usleep($base + $jitter);
                $clientToken = $this->braintreeCommon->generate_client_token($this->merchantAccountID);
                if ($clientToken) {
                    break;
                }
            }
        }
        
        return $clientToken;
    }

    /**
     * selection
     *
     * Generates and returns the HTML/JavaScript needed to render the PayPal payment button.
     *
     * @return array An associative array with module id, title, and form fields.
     */
    function selection() {
        global $order, $currencies;

        if (!$this->enabled) {
            return false;
        }

        try {
            $clientToken = (string) $this->generate_client_token();
        } catch (Exception $e) {
            $clientToken = '';
            if ($this->debug_logging) {
                error_log('Braintree PayPal: Failed to generate client token - ' . $e->getMessage());
            }
        }
        $tokenizationKey = $this->tokenizationKey;
        $hasTokenizationKey = ($tokenizationKey !== '');

        if ($clientToken === '' && $hasTokenizationKey) {
            error_log('Braintree PayPal: Falling back to the configured tokenization key because a client token was not available.');
        }

        if ($clientToken === '' && !$hasTokenizationKey) {
            return array(
                'id'     => $this->code,
                'module' => $this->title,
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

        $authorizationType = $clientToken !== '' ? 'clientToken' : 'tokenizationKey';
        $authorizationValue = $clientToken !== '' ? $clientToken : $tokenizationKey;
        $currency = $order->info['currency'] ?? ($_SESSION['currency'] ?? 'USD');
        $amount = number_format((float)$currencies->value($order->info['total'] ?? 0, $currency), 2, '.', '');
        $orderTotalsSelector = defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_TOTAL_SELECTOR') ? MODULE_PAYMENT_BRAINTREE_PAYPAL_TOTAL_SELECTOR : '#orderTotal';
        $billingCountryCode = $order->billing['country']['iso_code_2'] ?? '';
        $paypalLocale = $this->getPaypalLocaleByCountry($billingCountryCode);

        $config = array(
            'amount'              => $amount,
            'currency'            => $currency,
            'authorization'       => $authorizationValue,
            'clientToken'         => $clientToken,
            'tokenizationKey'     => $tokenizationKey,
            'authorizationType'   => $authorizationType,
            'orderTotalsSelector' => $orderTotalsSelector,
            'paypalLocale'        => $paypalLocale,
        );

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        ob_start();
        ?>
        <style>
            #paypal-button-container {
                margin: 0.5em 0;
            }

            #paypal-button-container p.paypal-unavailable {
                color: #666;
                font-size: 0.9em;
                margin: 0.5em 0;
            }
        </style>
        <div id="paypal-button-container"></div>
        <script>
        (function () {
            "use strict";

            const paypalConfig = <?php echo $configJson; ?>;
            const paypalDebugEnabled = true;

            window.paypalState = window.paypalState || {};

            if (typeof window.paypalState.setupPromise === "undefined") {
                window.paypalState.setupPromise = null;
            }
            if (typeof window.paypalState.initializationPromise === "undefined") {
                window.paypalState.initializationPromise = null;
            }
            if (typeof window.paypalState.clientInstance === "undefined") {
                window.paypalState.clientInstance = null;
            }
            if (typeof window.paypalState.paypalCheckoutInstance === "undefined") {
                window.paypalState.paypalCheckoutInstance = null;
            }
            if (typeof window.paypalState.lastAuthorization === "undefined") {
                window.paypalState.lastAuthorization = null;
            }
            if (typeof window.paypalState.lastAuthorizationType === "undefined") {
                window.paypalState.lastAuthorizationType = null;
            }
            if (typeof window.paypalState.sdkLoaded === "undefined") {
                window.paypalState.sdkLoaded = false;
            }

            window.braintreePayPalScriptsLoaded = window.braintreePayPalScriptsLoaded || false;

            function paypalLog() {
                if (!paypalDebugEnabled || typeof console === "undefined" || typeof console.log !== "function") {
                    return;
                }
                const args = Array.prototype.slice.call(arguments);
                args.unshift("PayPal:");
                console.log.apply(console, args);
            }

            function paypalWarn() {
                if (!paypalDebugEnabled || typeof console === "undefined") {
                    return;
                }
                const warn = typeof console.warn === "function" ? console.warn : console.log;
                const args = Array.prototype.slice.call(arguments);
                args.unshift("PayPal:");
                warn.apply(console, args);
            }

            function getAuthorizationDetails() {
                const configType = paypalConfig.authorizationType || "";
                const clientToken = typeof paypalConfig.clientToken === "string" ? paypalConfig.clientToken : "";
                const tokenizationKey = typeof paypalConfig.tokenizationKey === "string" ? paypalConfig.tokenizationKey : "";
                let type = configType;

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
                const explicitAuthorization = typeof paypalConfig.authorization === "string" ? paypalConfig.authorization : "";

                if (explicitAuthorization !== "") {
                    value = explicitAuthorization;
                    if (!type) {
                        if (explicitAuthorization === clientToken) {
                            type = "clientToken";
                        } else if (explicitAuthorization === tokenizationKey) {
                            type = "tokenizationKey";
                        }
                    }
                }

                if (value === "" && type === "clientToken" && clientToken !== "") {
                    value = clientToken;
                } else if (value === "" && type === "tokenizationKey" && tokenizationKey !== "") {
                    value = tokenizationKey;
                } else if (value === "" && clientToken !== "") {
                    type = "clientToken";
                    value = clientToken;
                } else if (value === "" && tokenizationKey !== "") {
                    type = "tokenizationKey";
                    value = tokenizationKey;
                }

                return {
                    value: value || "",
                    type: type || null
                };
            }

            function resetPayPalUI() {
                const container = document.getElementById("paypal-button-container");
                if (!container) {
                    return;
                }

                container.innerHTML = "";
                container.style.display = "";
                delete container.dataset.paypalButtonReady;
            }

            function ensureHiddenField(form, name, value) {
                if (!form) {
                    return;
                }

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
                const container = document.getElementById("paypal-button-container");
                if (!container) {
                    return;
                }

                container.innerHTML = "";
                container.style.display = "block";
                restorePayPalWrappers(container);

                const msg = document.createElement("p");
                msg.className = "paypal-unavailable";
                msg.textContent = message;
                container.appendChild(msg);
            }

            const paypalWrapperSelectors = [
                ".payment-method-item",
                ".payment-method",
                ".payment-method-option",
                ".payment-option",
                ".payment-option-item",
                ".custom-control",
                ".custom-radio",
                ".braintree_paypal",
                ".braintree-paypal"
            ];

            function getPayPalWrappers(elements) {
                const wrappers = [];
                const targets = Array.isArray(elements) ? elements : [elements];

                targets.forEach(function (el) {
                    if (!el) {
                        return;
                    }

                    paypalWrapperSelectors.forEach(function (selector) {
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

            function restorePayPalWrappers(container) {
                if (!container) {
                    return;
                }

                const wrappers = getPayPalWrappers([container]);
                wrappers.forEach(function (wrapper) {
                    const state = wrapper.dataset.paypalWrapperState;
                    if (state === "with-container") {
                        const originalDisplay = wrapper.dataset.paypalOriginalDisplay;
                        wrapper.style.display = typeof originalDisplay === "string" ? originalDisplay : "";
                        delete wrapper.dataset.paypalWrapperState;
                        delete wrapper.dataset.paypalOriginalDisplay;
                    }
                });
            }

            function hidePayPalOption(options) {
                options = options || {};
                const preserveContainer = options.preserveContainer === true;
                const hideWrapper = options.hideWrapper !== false;

                const paypalInputs = Array.from(new Set(Array.from(document.querySelectorAll("input[name='payment'][value='braintree_paypal'], #pmt-braintree_paypal"))));
                const paypalLabel = document.querySelector("label[for='pmt-braintree_paypal']");
                const container = document.getElementById("paypal-button-container");

                paypalInputs.forEach(function (input) {
                    if (input) {
                        input.style.display = "none";
                    }
                });

                if (paypalLabel) {
                    paypalLabel.style.display = "none";
                }

                if (container && !preserveContainer) {
                    container.style.display = "none";
                }

                if (!hideWrapper) {
                    return;
                }

                const wrappers = getPayPalWrappers(paypalInputs.concat([paypalLabel, container]));
                wrappers.forEach(function (wrapper) {
                    if (!wrapper) {
                        return;
                    }

                    const containsContainer = container && wrapper.contains(container);
                    const shouldHide = !containsContainer || !preserveContainer;

                    if (!shouldHide) {
                        return;
                    }

                    if (typeof wrapper.dataset.paypalOriginalDisplay === "undefined") {
                        wrapper.dataset.paypalOriginalDisplay = wrapper.style.display || "";
                    }
                    wrapper.style.display = "none";
                    wrapper.dataset.paypalWrapperState = containsContainer ? "with-container" : "without-container";
                });
            }

            function setupPayPalOption() {
                if (typeof window.paypalRadioHandlerCleanup === "function") {
                    window.paypalRadioHandlerCleanup();
                    window.paypalRadioHandlerCleanup = null;
                }

                const paypalInputs = Array.from(new Set(Array.from(document.querySelectorAll("input[name='payment'][value='braintree_paypal'], #pmt-braintree_paypal"))));
                const paypalLabel = document.querySelector("label[for='pmt-braintree_paypal']");
                const container = document.getElementById("paypal-button-container");

                if (paypalInputs.length === 0) {
                    return;
                }

                paypalInputs.forEach(function (input) {
                    if (input) {
                        input.style.display = "";
                    }
                });

                if (paypalLabel) {
                    paypalLabel.style.display = "";
                }

                if (container) {
                    restorePayPalWrappers(container);
                }

                function triggerPayPalFlow() {
                    const button = document.querySelector("#paypal-button-container button");
                    if (button) {
                        button.click();
                    }
                }

                paypalInputs.forEach(function (input) {
                    if (input) {
                        input.addEventListener("click", triggerPayPalFlow);
                        input.addEventListener("change", triggerPayPalFlow);
                    }
                });

                window.paypalRadioHandlerCleanup = function () {
                    paypalInputs.forEach(function (input) {
                        if (input) {
                            input.removeEventListener("click", triggerPayPalFlow);
                            input.removeEventListener("change", triggerPayPalFlow);
                        }
                    });
                };
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

                const paypalRadios = Array.from(new Set(Array.from(form.querySelectorAll("input[type='radio'][name='payment'][value='braintree_paypal'], #pmt-braintree_paypal"))));
                const paymentRadios = Array.from(form.querySelectorAll("input[type='radio'][name='payment']"));

                if (paypalRadios.length === 0 || paymentRadios.length === 0) {
                    return;
                }

                if (typeof window.paypalSubmitToggleCleanup === "function") {
                    window.paypalSubmitToggleCleanup();
                    window.paypalSubmitToggleCleanup = null;
                }

                function toggle() {
                    const isPayPalSelected = paypalRadios.some(function (radio) {
                        return radio && radio.checked;
                    });

                    submitBtn.style.display = isPayPalSelected ? "none" : "";
                }

                paymentRadios.forEach(function (radio) {
                    radio.addEventListener("change", toggle);
                });

                window.paypalSubmitToggleCleanup = function () {
                    paymentRadios.forEach(function (radio) {
                        radio.removeEventListener("change", toggle);
                    });
                };

                toggle();
            }

            function loadScript(src) {
                return new Promise(function (resolve, reject) {
                    if (document.querySelector('script[src="' + src + '"]')) {
                        paypalLog("Script already present", src);
                        resolve();
                        return;
                    }

                    const script = document.createElement("script");
                    script.src = src;
                    script.onload = function () {
                        paypalLog("Loaded script", src);
                        resolve();
                    };
                    script.onerror = function () {
                        paypalWarn("Failed to load script", src);
                        reject(new Error("Failed to load script: " + src));
                    };
                    document.body.appendChild(script);
                });
            }

            function loadPayPalScripts() {
                if (window.braintreePayPalScriptsLoaded) {
                    paypalLog("PayPal libraries already loaded");
                    return Promise.resolve();
                }

                const scripts = [
                    "https://js.braintreegateway.com/web/3.133.0/js/client.min.js",
                    "https://js.braintreegateway.com/web/3.133.0/js/paypal-checkout.min.js"
                ];

                paypalLog("Loading PayPal libraries sequentially", scripts);

                // Load scripts sequentially to avoid iOS Chrome compatibility issues
                return scripts.reduce(function (promise, src) {
                    return promise.then(function () {
                        return loadScript(src);
                    });
                }, Promise.resolve()).then(function () {
                    window.braintreePayPalScriptsLoaded = true;
                });
            }

            function renderPayPalButton(paypalCheckoutInstance) {
                const container = document.getElementById("paypal-button-container");
                if (!container) {
                    paypalWarn("PayPal button container not found");
                    return;
                }

                restorePayPalWrappers(container);

                container.innerHTML = "";
                container.style.display = "";

                const paypalInputsForRender = Array.from(new Set(Array.from(document.querySelectorAll("input[name='payment'][value='braintree_paypal'], #pmt-braintree_paypal"))));
                paypalInputsForRender.forEach(function (input) {
                    if (input) {
                        input.style.display = "";
                    }
                });

                const paypalLabelForRender = document.querySelector("label[for='pmt-braintree_paypal']");
                if (paypalLabelForRender) {
                    paypalLabelForRender.style.display = "";
                }

                paypalLog("Rendering PayPal button");

                paypal.Buttons({
                    createOrder: function () {
                        return paypalCheckoutInstance.createPayment({
                            flow: "checkout",
                            amount: paypalConfig.amount,
                            currency: paypalConfig.currency,
                            shippingAddressEditable: false
                        });
                    },
                    onClick: function () {
                        const radio = document.querySelector("input[name='payment'][value='braintree_paypal'], #pmt-braintree_paypal");
                        if (radio) {
                            radio.checked = true;
                            radio.dispatchEvent(new Event("change"));
                        }
                    },
                    onApprove: function (data) {
                        return paypalCheckoutInstance.tokenizePayment(data).then(function (payload) {
                            const form = document.getElementById("checkout_payment")
                                || document.querySelector("form[name='checkout_payment']");
                            if (!form) {
                                alert("Checkout form not found!");
                                return;
                            }

                            ensureHiddenField(form, "payment_method_nonce", payload.nonce);

                            const radio = document.querySelector("input[name='payment'][value='braintree_paypal'], #pmt-braintree_paypal");
                            if (radio) {
                                radio.checked = true;
                                radio.dispatchEvent(new Event("change"));
                            }

                            form.submit();
                        }).catch(function (err) {
                            paypalWarn("Tokenization failed", err);
                        });
                    },
                    onCancel: function (data) {
                        paypalLog("PayPal payment cancelled", data);
                    },
                    onError: function (err) {
                        paypalWarn("PayPal error", err);
                        renderUnavailableMessage("PayPal is temporarily unavailable. Please choose another payment method.");
                    }
                }).render("#paypal-button-container").then(function () {
                    const containerEl = document.getElementById("paypal-button-container");
                    if (containerEl) {
                        containerEl.dataset.paypalButtonReady = "true";
                    }
                }).catch(function (err) {
                    paypalWarn("PayPal button render failed", err);
                    renderUnavailableMessage("PayPal is temporarily unavailable. Please choose another payment method.");
                });
            }

            function initializePayPal() {
                const state = window.paypalState;
                const container = document.getElementById("paypal-button-container");

                if (!container) {
                    paypalWarn("PayPal button container missing during initialization");
                    hidePayPalOption();
                    return Promise.resolve();
                }

                restorePayPalWrappers(container);

                if (state.initializationPromise) {
                    paypalLog("PayPal initialization already in progress; reusing promise");
                    return state.initializationPromise;
                }

                const authorizationDetails = getAuthorizationDetails();
                const authorization = authorizationDetails.value;
                const authorizationType = authorizationDetails.type;

                if (!authorization) {
                    paypalWarn("Authorization credentials unavailable; skipping PayPal setup");
                    return Promise.resolve();
                }

                const needsNewClient = state.lastAuthorization !== authorization
                    || state.lastAuthorizationType !== authorizationType
                    || !state.clientInstance
                    || !state.paypalCheckoutInstance;

                if (!needsNewClient && state.sdkLoaded && container.dataset.paypalButtonReady === "true") {
                    paypalLog("PayPal button already rendered with current token; skipping initialization");
                    return Promise.resolve();
                }

                const initializationPromise = (needsNewClient
                    ? braintree.client.create({ authorization: authorization }).then(function (clientInstance) {
                        state.clientInstance = clientInstance;
                        state.lastAuthorization = authorization;
                        state.lastAuthorizationType = authorizationType;
                        return braintree.paypalCheckout.create({ client: clientInstance }).then(function (paypalCheckoutInstance) {
                            state.paypalCheckoutInstance = paypalCheckoutInstance;
                            state.sdkLoaded = false;
                            return paypalCheckoutInstance;
                        });
                    })
                    : Promise.resolve(state.paypalCheckoutInstance)
                ).then(function (paypalCheckoutInstance) {
                    if (!paypalCheckoutInstance) {
                        throw new Error("PayPal Checkout instance unavailable");
                    }

                    if (state.sdkLoaded) {
                        paypalLog("PayPal SDK already loaded; rendering button");
                        renderPayPalButton(paypalCheckoutInstance);
                        return;
                    }

                    const loadOptions = { currency: paypalConfig.currency };
                    if (paypalConfig.paypalLocale) {
                        loadOptions.locale = paypalConfig.paypalLocale;
                    }

                    return paypalCheckoutInstance.loadPayPalSDK(loadOptions).then(function () {
                        state.sdkLoaded = true;
                        renderPayPalButton(paypalCheckoutInstance);
                    });
                }).catch(function (err) {
                    state.clientInstance = null;
                    state.paypalCheckoutInstance = null;
                    state.lastAuthorization = null;
                    state.lastAuthorizationType = null;
                    state.sdkLoaded = false;
                    paypalWarn("PayPal setup error", err);
                    renderUnavailableMessage("PayPal is temporarily unavailable. Please choose another payment method.");
                });

                state.initializationPromise = initializationPromise;

                initializationPromise.then(function () {
                    state.initializationPromise = null;
                }, function () {
                    state.initializationPromise = null;
                });

                return initializationPromise;
            }

            function runWhenDocumentReady(callback) {
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", callback);
                } else {
                    callback();
                }
            }

            function preparePayPalSetup() {
                const state = window.paypalState;

                resetPayPalUI();
                setupPayPalOption();
                initSubmitButtonToggle();

                if (state.setupPromise) {
                    paypalLog("Reusing cached PayPal setup promise");
                    return state.setupPromise;
                }

                const setupPromise = loadPayPalScripts().then(function () {
                    return initializePayPal();
                });

                state.setupPromise = setupPromise;

                setupPromise.then(function () {
                    state.setupPromise = null;
                }, function () {
                    state.setupPromise = null;
                });

                return setupPromise;
            }

            runWhenDocumentReady(preparePayPalSetup);

            if (window.paypalReloadHandler) {
                document.removeEventListener("onePageCheckoutReloaded", window.paypalReloadHandler);
            }

            window.paypalReloadHandler = function () {
                preparePayPalSetup();
            };

            document.addEventListener("onePageCheckoutReloaded", window.paypalReloadHandler);
        })();
        </script>
        <?php
        $output = ob_get_clean();

        return array(
            'id' => $this->code,
            'module' => $this->title,
            'fields' => array(
                array(
                    'title' => '',
                    'field' => $output
                )
            )
        );
    }

    function getPaypalLocaleByCountry($countryIso) {
        // Mapping of ISO country codes to PayPal locales
        $paypalLocales = array(
            'DK' => 'da_DK',
            'DE' => 'de_DE',
            'AU' => 'en_AU',
            'GB' => 'en_GB',
            'US' => 'en_US',
            'ES' => 'es_ES',
            'FR' => 'fr_FR',
            'CA' => 'en_CA',
            'ID' => 'id_ID',
            'IT' => 'it_IT',
            'JP' => 'ja_JP',
            'KR' => 'ko_KR',
            'NL' => 'nl_NL',
            'NO' => 'no_NO',
            'PL' => 'pl_PL',
            'BR' => 'pt_BR',
            'PT' => 'pt_PT',
            'RU' => 'ru_RU',
            'SE' => 'sv_SE',
            'TH' => 'th_TH',
            'CN' => 'zh_CN',
            'HK' => 'zh_HK',
            'TW' => 'zh_TW'
        );

        $countryIso = strtoupper($countryIso);

        if (isset($paypalLocales[$countryIso])) {
            return $paypalLocales[$countryIso];
        }

        // Fallback: try STORE_COUNTRY
        if (defined('STORE_COUNTRY') && STORE_COUNTRY != '') {
            $storeCountryIso = zen_get_country_iso_code_2(STORE_COUNTRY);
            if (isset($paypalLocales[$storeCountryIso])) {
                return $paypalLocales[$storeCountryIso];
            }
        }

        // Final fallback: let PayPal handle the default
        return '';
    }

    /**
     * javascript_validation
     *
     * No JavaScript validation is required for this module.
     *
     * @return bool Always returns false.
     */
    function javascript_validation() {
        return false;
    }

    /**
     * pre_confirmation_check
     *
     * Checks that a payment nonce has been submitted. If not, it adds an error message and redirects.
     */
    function pre_confirmation_check() {
        global $messageStack;
        if (empty($_POST['payment_method_nonce'])) {
            $messageStack->add_session('checkout_payment', MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_FAILED, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
        }
        $this->nonce = $_SESSION['payment_method_nonce'] = $_POST['payment_method_nonce'];
    }

    /**
     * confirmation
     *
     * No confirmation fields are required for this module.
     *
     * @return bool Always returns false.
     */
    function confirmation() {
        return false;
    }

    /**
     * process_button
     *
     * Generates a hidden field containing the payment nonce to be submitted with the form.
     *
     * @return string HTML hidden field.
     */
    function process_button() {
        return zen_draw_hidden_field('payment_method_nonce', $_POST['payment_method_nonce']);
    }

    /**
     * process_button_ajax
     *
     * Prepares AJAX response data including the nonce and payment type for dynamic processing.
     *
     * @return array An array of extra fields and credit card fields.
     */
    function process_button_ajax() {
        global $order;
        $processButton = [
            'ccFields' => [],
            'extraFields' => [zen_session_name() => zen_session_id()]
        ];
        if (!empty($_POST['payment_method_nonce'])) {
            $processButton['ccFields']['bt_nonce'] = $_POST['payment_method_nonce']; // PayPal nonce
            $processButton['ccFields']['bt_payment_type'] = 'paypal';
            $processButton['ccFields']['bt_currency_code'] = $order->info['currency'];
        }
        return $processButton;
    }

    /**
     * before_process
     *
     * Delegates payment processing to the common before_process_common function,
     * passing the merchant account ID.
     *
     * @return bool True on success; otherwise, redirects on failure.
     */
    function before_process() {
        return $this->braintreeCommon->before_process_common($this->merchantAccountID, array(), (MODULE_PAYMENT_BRAINTREE_PAYPAL_SETTLEMENT == 'true'));
    }

    /**
     * After the transaction is processed, update order status history and store transaction details.
     * Uses the Pending status if the payment was not captured (i.e. settlement is false).
     */
    function after_process() {
        global $insert_id, $db, $order;

        // Retrieve transaction details from session
        $txnId = $_SESSION['braintree_transaction_id'] ?? '';
        $paymentStatus = $_SESSION['braintree_payment_status'] ?? 'Pending';
        $currency = $_SESSION['braintree_currency'] ?? '';
        $amount = $_SESSION['braintree_amount'] ?? 0;

        // Determine order status based on transaction settlement
        $orderStatus = (MODULE_PAYMENT_BRAINTREE_PAYPAL_SETTLEMENT == 'true')
            ? MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS
            : MODULE_PAYMENT_BRAINTREE_PAYPAL_UNPAID_STATUS_ID;

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
                'comments'          => "Braintree PayPal Transaction ID: " . $txnId,
                'customer_notified' => 1
            ];
            zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

            // Insert transaction details into Braintree table
            $paypal_order = [
                'order_id'            => (int)$insert_id,
                'txn_id'              => $txnId,
                'txn_type'            => 'sale',
                'module_name'         => 'braintree_paypal',
                'payment_type'        => 'PayPal',
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
                'payer_email'         => $order->customer['email_address'] ?? '',
                'payment_date'        => date('Y-m-d'),
                'settle_amount'       => (float)$amount,
                'settle_currency'     => $currency,
                'exchange_rate'       => 0,
                'date_added'          => date('Y-m-d'),
                'module_mode'         => 'paypal'
            ];
            zen_db_perform(TABLE_BRAINTREE, $paypal_order);
        } else {
            error_log("Braintree PayPal Error: Missing transaction ID for order ID: $insert_id.");
        }

        // Cleanup session variables
        unset($_SESSION['braintree_transaction_id'], $_SESSION['braintree_payment_status'], $_SESSION['braintree_currency'], $_SESSION['braintree_amount']);
    }

    /**
     * getTransactionId
     *
     * Delegates retrieving the transaction ID for a given order to the common class.
     *
     * @param int $orderId The order ID.
     * @return string|null The transaction ID or null if not found.
     */
    function getTransactionId($orderId) {
        return $this->braintreeCommon->getTransactionId($orderId);
    }

    /**
     * _GetTransactionDetails
     *
     * Delegates retrieving transaction details to the common class.
     *
     * @param int $oID The order ID.
     * @return array An associative array with transaction details.
     */
    function _GetTransactionDetails($oID) {
        return $this->braintreeCommon->_GetTransactionDetails($oID);
    }

    /**
     * _doRefund
     *
     * Delegates processing a refund to the common class.
     *
     * @param int $oID The order ID.
     * @param mixed $amount The refund amount ('Full' for full refund or a specific amount).
     * @param string $note An optional note for the refund.
     * @return bool True on success, false on failure.
     */
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
            MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS, // Use the module-specific paid status configuration
            $this->code
        );
    }

    /**
     * install
     *
     * Inserts configuration settings into the database and calls upgrade().
     */
    function install() {
        global $db;

        // Ensure the Braintree table exists via the common class.
        $this->braintreeCommon->create_braintree_table();
    }

    /**
     * check
     *
     * Checks if the module is installed by verifying its configuration.
     *
     * @return int The number of configuration records found.
     */
    function check() {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * keys
     *
     * Returns an array of configuration keys in the order they should display.
     *
     * @return array List of configuration key strings.
     */
    function keys() {
        return [
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_VERSION',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_SERVER',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_MERCHANT_KEY',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_PUBLIC_KEY',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_PRIVATE_KEY',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_PAYMENT_FAILED',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_SETTLEMENT',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_ORDER_STATUS',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_UNPAID_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_PENDING_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_TOTAL_SELECTOR',
            /* the following two options are not supported yet by Braintree except for virtual products */
            //'MODULE_PAYMENT_BRAINTREE_PAYPAL_SHOPPING_CART',
            //'MODULE_PAYMENT_BRAINTREE_PAYPAL_PRODUCT_PAGE',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_DEBUGGING',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_ZONE',
            'MODULE_PAYMENT_BRAINTREE_PAYPAL_SORT_ORDER'
        ];
    }

    /**
     * remove
     *
     * Deletes the module's configuration settings from the database and notifies the admin.
     */
    function remove() {
        global $db, $messageStack;
        $keys = implode("', '", $this->keys());
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('$keys')");
        $messageStack->add_session(NOTIFY_PAYMENT_BRAINTREE_UNINSTALLED, 'success');
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

    /**
     * admin_notification
     *
     * Retrieves transaction details and includes an admin notification if available.
     *
     * @param int $zf_order_id The order ID.
     * @return string Notification output.
     */
    function admin_notification($zf_order_id) {
        if (!defined('MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS')) {
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
}