<?php
// braintree_api.php payment module class
// needs to be loaded even in admin for edit orders
use Braintree\Gateway;
use Braintree\Transaction;

require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/lib/Braintree.php');
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_common.php');

if (!defined('TABLE_BRAINTREE')) {
  define('TABLE_BRAINTREE', DB_PREFIX . 'braintree');
}

class braintree_api extends base {
    public $code = 'braintree_api';
    public $title;
    public $description;
    public $enabled;
    public $zone;
    public $sort_order = null;
    public $order_pending_status = 1;
    public $order_status = DEFAULT_ORDERS_STATUS_ID;
    public $transaction_id;
    public $payment_type;
    public $payment_status;
    public $avs;
    public $cvv2;
    public $payment_time;
    public $amt;
    public $transactiontype;
    public $numitems;
    public $debug_logging;
    public $merchantAccountID;
    public $nonce;
    public $tokenizationKey = '';
    private $_check;
    // Credit card details for admin display purposes:
    public $cc_card_type;
    public $cc_card_number;
    public $cc_expiry_month;
    public $cc_expiry_year;
    public $cc_checkcode;
    public $cc_type_check;

    /**
     * Instance of the shared Braintree common class.
     * @var BraintreeCommon
     */
    private $braintreeCommon;

    /**
     * Constructor.
     */
    function __construct() {
        global $order;

        // Admin titles/description
        if (IS_ADMIN_FLAG === true) {
            $this->title = MODULE_PAYMENT_BRAINTREE_TEXT_ADMIN_TITLE;
            $this->description = sprintf(MODULE_PAYMENT_BRAINTREE_TEXT_ADMIN_DESCRIPTION, (defined('MODULE_PAYMENT_BRAINTREE_VERSION') ? ' (rev' . MODULE_PAYMENT_BRAINTREE_VERSION . ')' : ''));
            $signupUrl = MODULE_PAYMENT_BRAINTREE_SIGNUP_URL;
            $latestInstallerVersion = $this->getLatestInstallerVersion();
            $upgradeAvailable = $this->isUpgradeAvailable($latestInstallerVersion);
            $upgradeMessage = '';
            $upgradeButtonHtml = '';

            if (!empty($_SESSION['braintreeUpgradeCompleted'])) {
                $upgradeAvailable = false;
                unset($_SESSION['braintreeUpgradeCompleted']);
            }

            if ($upgradeAvailable) {
                $upgradeMessage = '<p class="braintree-upgrade-notice">' . sprintf(MODULE_PAYMENT_BRAINTREE_UPGRADE_AVAILABLE, htmlspecialchars($latestInstallerVersion, ENT_QUOTES, 'UTF-8')) . '</p>';
                $upgradeButtonHtml = $this->buildUpgradeButton($latestInstallerVersion);
            }

            $signupHtml = '<div class="braintree-signup">'
                . '<h4>' . MODULE_PAYMENT_BRAINTREE_SIGNUP_HEADLINE . '</h4>'
                . '<p>' . MODULE_PAYMENT_BRAINTREE_SIGNUP_DESCRIPTION . '</p>'
                . $upgradeMessage
                . '<div class="braintree-signup-actions">'
                . '<a class="btn btn-primary" href="' . htmlspecialchars($signupUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . MODULE_PAYMENT_BRAINTREE_SIGNUP_LINK_TEXT . '</a>'
                . $upgradeButtonHtml
                . '</div>'
                . '</div>';

            $this->description .= $signupHtml;
        } else {
            $this->title = MODULE_PAYMENT_BRAINTREE_TEXT_TITLE;
            $this->description = MODULE_PAYMENT_BRAINTREE_TEXT_DESCRIPTION;
        }

        $this->enabled = defined('MODULE_PAYMENT_BRAINTREE_STATUS') && MODULE_PAYMENT_BRAINTREE_STATUS == 'True';
        $this->sort_order = defined('MODULE_PAYMENT_BRAINTREE_SORT_ORDER') ? MODULE_PAYMENT_BRAINTREE_SORT_ORDER : null;
        $this->zone = (int)(defined('MODULE_PAYMENT_BRAINTREE_ZONE') ? MODULE_PAYMENT_BRAINTREE_ZONE : 0);

        $this->order_status = (defined('MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID') && MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID > 0)
            ? MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID
            : (isset($order->info['order_status']) ? $order->info['order_status'] : 0);

        $this->debug_logging = defined('MODULE_PAYMENT_BRAINTREE_DEBUGGING') && MODULE_PAYMENT_BRAINTREE_DEBUGGING != 'Alerts Only';

        $config = [ 'debug_logging' => $this->debug_logging ];

        if (defined('MODULE_PAYMENT_BRAINTREE_TOKENIZATION_KEY')) {
            $this->tokenizationKey = trim(MODULE_PAYMENT_BRAINTREE_TOKENIZATION_KEY);
            if ($this->tokenizationKey !== '') {
                $config['tokenization_key'] = $this->tokenizationKey;
            }
        }

        if (defined('MODULE_PAYMENT_BRAINTREE_TIMEOUT')) {
            $timeout = (int) MODULE_PAYMENT_BRAINTREE_TIMEOUT;
            if ($timeout > 0) {
                $config['timeout'] = $timeout;
            }
        }

        if ($this->enabled) {
            $config = array_merge($config, [
                'environment' => MODULE_PAYMENT_BRAINTREE_SERVER,
                'merchant_id' => MODULE_PAYMENT_BRAINTREE_MERCHANTID,
                'public_key'  => MODULE_PAYMENT_BRAINTREE_PUBLICKEY,
                'private_key' => MODULE_PAYMENT_BRAINTREE_PRIVATEKEY
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
     * Sets module status based on zone restrictions and other rules.
     */
    function update_status() {
        global $order, $db;
        // If not in SSL (for PCI reasons) and not in admin, disable.
        if (IS_ADMIN_FLAG === false && (!defined('ENABLE_SSL') || ENABLE_SSL != 'true')) {
            $this->enabled = false;
        }
        if ($this->enabled && (int)$this->zone > 0) {
            $check_flag = false;
            $sql = "SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . "
                    WHERE geo_zone_id = :zoneId
                      AND zone_country_id = :countryId
                    ORDER BY zone_id";
            $sql = $db->bindVars($sql, ':zoneId', $this->zone, 'integer');
            $sql = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');
            $check = $db->Execute($sql);
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1 || $check->fields['zone_id'] == $order->billing['zone_id']) {
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
            if ($order_amount > 10000 || $order->info['total'] == 0) {
                $this->enabled = false;
            }
        }
    }

    /**
     * Return JavaScript validation code.
     */
    function javascript_validation() {
        return 'if(typeof braintreeCheck === "function" && !braintreeCheck()) return false;';
    }

    /**
     * Generate the Braintree client token
     */
    function generate_client_token() {
        return $this->braintreeCommon->generate_client_token($this->merchantAccountID);
    }

    function selection() {
        global $order;

        if (!$this->enabled) return false;

        $this->cc_type_check = 'var value = document.checkout_payment.braintree_cc_type.value;' .
            'if(value == "Solo" || value == "Maestro" || value == "Switch") {' .
            '    document.checkout_payment.braintree_cc_issue_month.disabled = false;' .
            '    document.checkout_payment.braintree_cc_issue_year.disabled = false;' .
            '    document.checkout_payment.braintree_cc_checkcode.disabled = false;' .
            '    if(document.checkout_payment.braintree_cc_issuenumber) document.checkout_payment.braintree_cc_issuenumber.disabled = false;' .
            '} else {' .
            '    if(document.checkout_payment.braintree_cc_issuenumber) document.checkout_payment.braintree_cc_issuenumber.disabled = true;' .
            '    if(document.checkout_payment.braintree_cc_issue_month) document.checkout_payment.braintree_cc_issue_month.disabled = true;' .
            '    if(document.checkout_payment.braintree_cc_issue_year) document.checkout_payment.braintree_cc_issue_year.disabled = true;' .
            '    document.checkout_payment.braintree_cc_checkcode.disabled = false;' .
            '}';
        if (empty($this->cards)) {
            $this->cc_type_check = '';
        }

        $fieldsArray = [];

        if (!empty($this->cc_type_check)) {
            $fieldsArray[] = [
                "field" => '<script type="text/javascript">function braintree_cc_type_check() { ' . $this->cc_type_check . ' } </script>'
            ];
        }

        if (isset($this->cards) && is_countable($this->cards) && sizeof($this->cards) > 0) {
            $fieldsArray[] = [
                'title' => MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_TYPE,
                'field' => zen_draw_pull_down_menu(
                    'braintree_cc_type',
                    $this->cards,
                    '',
                    'onchange="braintree_cc_type_check();" onblur="braintree_cc_type_check();" id="' . $this->code . '-cc-type"'
                ),
                'tag' => $this->code . '-cc-type'
            ];
        }

        $fieldsArray[] = [
            'title' => MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_NUMBER,
            'field' => '<div id="braintree_api-cc-number-hosted"></div><div id="braintree_error_number" class="error-msg"></div>'
        ];
        $fieldsArray[] = [
            'title' => MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_EXPIRES,
            'field' => '<div id="braintree_expiry-hosted"></div><div id="braintree_error_expirationDate" class="error-msg"></div>'
        ];
        $fieldsArray[] = [
            'title' => MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_CHECKNUMBER,
            'field' => '<div id="braintree_api-cc-cvv-hosted"></div><div id="braintree_error_cvv" class="error-msg"></div>'
        ];

        $use3DSFlag = (defined('MODULE_PAYMENT_BRAINTREE_USE_3DS') && MODULE_PAYMENT_BRAINTREE_USE_3DS === 'True');
        $clientToken = $this->generate_client_token();
        $tokenizationKey = $this->tokenizationKey;
        $hasTokenizationKey = ($tokenizationKey !== '');

        // If 3DS is enabled and client token generation failed, retry with exponential backoff
        if (!$clientToken && $use3DSFlag) {
            error_log('Braintree: Initial client token generation failed with 3DS enabled. Attempting retry...');
            
            // Retry up to 2 more times with exponential backoff (200ms, 500ms, 1s) plus jitter
            $retryDelays = [200000, 500000, 1000000]; // microseconds: 200ms, 500ms, 1s
            foreach ($retryDelays as $baseDelay) {
                // Add jitter: ±30% randomization to prevent thundering herd
                $jitter = random_int((int)(-$baseDelay * 0.3), (int)($baseDelay * 0.3));
                $delay = $baseDelay + $jitter;
                usleep($delay);
                $clientToken = $this->generate_client_token();
                if ($clientToken) {
                    error_log('Braintree: Client token generation succeeded on retry.');
                    break;
                }
            }
        }

        // Handle cases where client token is still not available after retries
        if (!$clientToken && $hasTokenizationKey && !$use3DSFlag) {
            error_log('Braintree: Falling back to the configured tokenization key because a client token was not available.');
        }

        if (!$clientToken && $hasTokenizationKey && $use3DSFlag) {
            // 3DS is required but client token generation failed - hide payment method to prevent checkout failure
            error_log('Braintree: 3DS is enabled but client token generation failed after retries. Hiding payment method to prevent 3DS authentication failure.');
            return false;
        }

        if (!$clientToken && !$hasTokenizationKey) {
            $fieldsArray = [
                [
                    'title' => '',
                    'field' => '<div style="color: red; font-weight: bold; padding: 1em; border: 1px solid red; background-color: #ffeeee;">
                                    Incorrect Braintree Configuration. Please contact the store administrator.
                                </div>'
                ]
            ];

            return [
                'id'     => $this->code,
                'module' => MODULE_PAYMENT_BRAINTREE_TEXT_TITLE,
                'fields' => $fieldsArray
            ];
        }

        $clientTokenEscaped = htmlspecialchars((string)$clientToken, ENT_QUOTES, 'UTF-8');
        $tokenizationKeyEscaped = htmlspecialchars($tokenizationKey, ENT_QUOTES, 'UTF-8');

        $autoStylingEnabled = (defined('MODULE_PAYMENT_BRAINTREE_AUTOMATE_STYLING') && MODULE_PAYMENT_BRAINTREE_AUTOMATE_STYLING === 'True') ? 'true' : 'false';
        $customCssJson = (defined('MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE') && trim(MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE) !== '') ? MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE : '{}';
        $orderTotalsSelector = defined('MODULE_PAYMENT_BRAINTREE_TOTAL_SELECTOR') ? MODULE_PAYMENT_BRAINTREE_TOTAL_SELECTOR : '#orderTotal';
        $use3DS = $use3DSFlag ? 'true' : 'false';
        $iframeCss = defined('MODULE_PAYMENT_BRAINTREE_HOSTED_IFRAME_CSS') ? MODULE_PAYMENT_BRAINTREE_HOSTED_IFRAME_CSS : '';

        // Combine all hidden data (CSS and JavaScript) into a single field to prevent extra div rows.
        // This consolidates what were previously three separate $fieldsArray items into one,
        // reducing unnecessary div rows in the checkout display.
        $hiddenFieldContent = '';
        
        // Add optional iframe CSS
        if (!empty($iframeCss)) {
            $hiddenFieldContent .= '<style>' . $iframeCss . '</style>';
        }
        
        // Add default CSS styles
        $hiddenFieldContent .= '
                <style>
                    .braintree-field-error {
                        color: #ee1b23;
                        font-size: 0.95em;
                        margin-top: 0.3em;
                        display: block;
                    }
                    .braintree-field-error svg {
                        vertical-align: middle;
                        margin-right: 0.4em;
                    }
                    .braintree-field-error span {
                        display: flex;
                        align-items: center;
                        gap: 0.4em;
                    }
                    .braintree-field-error span i {
                        vertical-align: middle;
                    }
                    .cardinalOverlay-content.cardinalOverlay-open {
                        border-radius: 20px;
                    }
                    div#Cardinal-Modal {
                        width: 80%;
                        height: auto;
                    }
                    iframe#Cardinal-CCA-IFrame {
                        width: 600px !important;
                        margin: 0 auto;
                        height: 100%;
                    }
                    iframe#Cardinal-CCA-IFrame #page-wrapper {
                        padding: 0 !important;
                        border: 0 !important;
                        max-width: 100% !important;
                    }
                    iframe#Cardinal-CCA-IFrame #page-wrapper h2 {
                        display: none;
                    }
                    #content.canvas .row h2 {
                        display: none;
                    }
                    div#Cardinal-Modal #Cardinal-ModalContent.size-02 {
                        height: 600px;
                    }
                    @media (max-width: 1024px) {
                        div#Cardinal-Modal {
                            width: auto;
                            height: auto;
                        }
                    }
                    #braintree_submit_message {
                        color: #EE1B23;
                        margin-bottom: 1em;
                        font-size: 0.95em;
                    }
                </style>';
        
        // Add hidden input fields and JavaScript
        $hiddenFieldContent .= "
                <input type='hidden' name='payment_method_nonce' id='payment_method_nonce' />
                <input type='hidden' id='braintree_client_token' value='{$clientTokenEscaped}' />
                <input type='hidden' id='braintree_tokenization_key' value='{$tokenizationKeyEscaped}' />
                <script defer>
                (function(){
                    'use strict';

                    console.log('Credit Card: Initializing hosted fields controller');

                    const is3DSEnabled = {$use3DS};

                    window.braintreeHostedFieldsInitialized = window.braintreeHostedFieldsInitialized || false;
                    window.braintreeScriptsLoaded = window.braintreeScriptsLoaded || false;
                    window.braintreeLastAuthorization = window.braintreeLastAuthorization || null;
                    window.braintreeClientInstance = window.braintreeClientInstance || null;
                    window.braintreeRetryAttempts = window.braintreeRetryAttempts || 0;
                    const MAX_RETRY_ATTEMPTS = 3;

                    function showRetryButton(message) {
                        const hostedFieldsContainer = document.getElementById('braintree_api-cc-number-hosted');
                        if (!hostedFieldsContainer || !hostedFieldsContainer.parentElement) return;

                        // Hide all hosted field containers
                        const fieldsToHide = ['braintree_api-cc-number-hosted', 'braintree_expiry-hosted', 'braintree_api-cc-cvv-hosted'];
                        fieldsToHide.forEach(function(fieldId) {
                            const container = document.getElementById(fieldId);
                            if (container) container.style.display = 'none';
                        });

                        // Create or update retry message container
                        let retryContainer = document.getElementById('braintree-retry-container');
                        if (!retryContainer) {
                            retryContainer = document.createElement('div');
                            retryContainer.id = 'braintree-retry-container';
                            retryContainer.style.cssText = 'padding: 1em; border: 1px solid #ee1b23; background-color: #ffeeee; margin: 1em 0; border-radius: 4px;';
                            hostedFieldsContainer.parentElement.insertBefore(retryContainer, hostedFieldsContainer);
                        }

                        retryContainer.innerHTML = '<strong>Payment Fields Unavailable</strong><br><br>' +
                            message + '<br><br>' +
                            '<button type=\"button\" id=\"braintree-retry-btn\" style=\"padding: 0.5em 1em; background-color: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em;\">' +
                            'Retry Now</button>';

                        const retryBtn = document.getElementById('braintree-retry-btn');
                        if (retryBtn) {
                            retryBtn.onclick = function() {
                                window.braintreeRetryAttempts++;
                                retryContainer.style.display = 'none';
                                fieldsToHide.forEach(function(fieldId) {
                                    const container = document.getElementById(fieldId);
                                    if (container) container.style.display = 'block';
                                });
                                console.log('Credit Card: Manual retry attempt ' + window.braintreeRetryAttempts);
                                window.braintreeHostedFieldsInitialized = false;
                                window.braintreeScriptsLoaded = false;
                                window.braintreeInitializing = false;
                                loadBraintreeHostedFieldsScripts(function() {
                                    initializeBraintreeHostedFields();
                                });
                            };
                        }
                    }

                    function handleInitializationFailure(error) {
                        console.error('Credit Card: Initialization failed:', error);
                        window.braintreeInitializing = false;
                        window.braintreeRetryAttempts++;
                        
                        if (window.braintreeRetryAttempts >= MAX_RETRY_ATTEMPTS) {
                            // Show retry button after exhausting automatic retries
                            const authorization = getAuthorizationDetails();
                            if (authorization.type === 'tokenizationKey' && authorization.value) {
                                showRetryButton('The payment form could not load automatically. Click the button below to try again.');
                            } else {
                                showRetryButton('The payment form is currently unavailable. Please refresh the page or select a different payment method. If the problem persists, contact the store administrator.');
                            }
                        } else {
                            // Automatic retry with exponential backoff
                            const delay = Math.min(1000 * Math.pow(2, window.braintreeRetryAttempts - 1), 5000);
                            console.log('Credit Card: Retrying in ' + delay + 'ms (attempt ' + window.braintreeRetryAttempts + ' of ' + MAX_RETRY_ATTEMPTS + ')');
                            setTimeout(function() {
                                window.braintreeScriptsLoaded = false;
                                window.braintreeHostedFieldsInitialized = false;
                                window.braintreeInitializing = false;
                                loadBraintreeHostedFieldsScripts(function() {
                                    initializeBraintreeHostedFields();
                                });
                            }, delay);
                        }
                    }

                    function loadBraintreeHostedFieldsScripts(callback) {
                        if (window.braintreeScriptsLoaded) {
                            console.log('Credit Card: Hosted Fields scripts already loaded; continuing initialization');
                            callback();
                            return;
                        }
                        const scripts = [
                            \"https://js.braintreegateway.com/web/3.115.2/js/client.min.js\",
                            \"https://js.braintreegateway.com/web/3.115.2/js/three-d-secure.js\",
                            \"https://js.braintreegateway.com/web/3.115.2/js/hosted-fields.js\"
                        ];
                        let loadedCount = 0;
                        console.log('Credit Card: Loading Hosted Fields resources', scripts);
                        scripts.forEach(function(src, index) {
                            if (document.querySelector('script[src=\"${src}\"]')) {
                                loadedCount++;
                                console.log('Credit Card: Script already present ' + src);
                                if (loadedCount === scripts.length) {
                                    window.braintreeScriptsLoaded = true;
                                    console.log('Credit Card: Hosted Fields scripts ready; invoking callback');
                                    callback();
                                }
                                return;
                            }
                            const script = document.createElement(\"script\");
                            script.src = src;
                            script.onload = function() {
                                loadedCount++;
                                console.log('Credit Card: Loaded script ' + src + ' ' + loadedCount + '/' + scripts.length);
                                if (loadedCount === scripts.length) {
                                    window.braintreeScriptsLoaded = true;
                                    console.log('Credit Card: Hosted Fields scripts ready; invoking callback');
                                    callback();
                                }
                            };
                            script.onerror = function() {
                                console.error('Credit Card: Failed to load script:', src);
                                handleInitializationFailure(new Error('Failed to load script: ' + src));
                            };
                            document.body.appendChild(script);
                        });
                    }

                    function getAuthorizationDetails() {
                        const tokenField = document.getElementById('braintree_client_token');
                        if (tokenField && tokenField.value) {
                            return { value: tokenField.value, type: 'clientToken' };
                        }

                        const tokenizationKeyField = document.getElementById('braintree_tokenization_key');
                        if (tokenizationKeyField && tokenizationKeyField.value) {
                            return { value: tokenizationKeyField.value, type: 'tokenizationKey' };
                        }

                        return { value: null, type: null };
                    }

                    function initializeBraintreeHostedFields() {
                        const useAutoStyle = {$autoStylingEnabled};
                        let customStyles = {};
                        function selectBraintreeRadio() {
                            const radio = document.getElementById('pmt-braintree_api');
                            if (radio && !radio.checked) {
                                radio.checked = true;
                                radio.dispatchEvent(new Event('change'));
                            }
                        }
                        try {
                            customStyles = " . $customCssJson . ";
                        } catch (e) {
                            console.warn('Credit Card: Invalid custom style JSON in config:', e);
                        }

                        console.log('Credit Card: initializeBraintreeHostedFields invoked');

                        const authorization = getAuthorizationDetails();
                        const authorizationValue = authorization.value;
                        const authorizationType = authorization.type;
                        const shouldUse3DS = is3DSEnabled && authorizationType === 'clientToken';

                        if (!authorizationValue) {
                            console.warn('Credit Card: Authorization credentials missing; deferring initialization');
                            return;
                        }

                        if (is3DSEnabled && authorizationType !== 'clientToken') {
                            console.error('Credit Card: 3DS is enabled but requires a client token. Falling back without 3DS support.');
                        }

                        console.log('Credit Card: Using ' + (authorizationType === 'clientToken' ? 'client token' : 'tokenization key') + ' for authorization');

                        const numberContainer = document.getElementById('braintree_api-cc-number-hosted');
                        if (numberContainer && numberContainer.querySelector('iframe')) {
                            console.log('Credit Card: Hosted Fields already attached — skipping reinitialization');
                            window.braintreeHostedFieldsInitialized = true;
                            return;
                        }

                        function extractInputStyles(selector = 'input[type=\"text\"]') {
                            const el = document.querySelector(selector);
                            if (!el) return {};
                            const computed = window.getComputedStyle(el);
                            return {
                                'input': {
                                    'font-size': computed.fontSize,
                                    'font-family': computed.fontFamily,
                                    'color': computed.color,
                                    'background-color': computed.backgroundColor,
                                    'border': computed.border,
                                    'padding': computed.padding,
                                    'margin': computed.margin,
                                    'box-shadow': computed.boxShadow,
                                    'line-height': computed.lineHeight,
                                    'letter-spacing': computed.letterSpacing
                                },
                                ':focus': {
                                    'color': computed.color
                                },
                                '.invalid': {
                                    'color': 'red'
                                },
                                '.valid': {
                                    'color': 'green'
                                }
                            };
                        }

                        function setupHostedFields(clientInstance) {
                            // Prevent concurrent initialization attempts
                            if (window.braintreeInitializing) {
                                console.log('Credit Card: Initialization already in progress, skipping duplicate attempt');
                                return;
                            }
                            window.braintreeInitializing = true;

                            // Helper function to clear any lingering iframes from the DOM
                            function clearHostedFieldsIframes() {
                                ['braintree_api-cc-number-hosted', 'braintree_api-cc-cvv-hosted', 'braintree_expiry-hosted'].forEach(function(id) {
                                    const container = document.getElementById(id);
                                    if (container) {
                                        const existingIframe = container.querySelector('iframe');
                                        if (existingIframe) {
                                            console.log('Credit Card: Removing lingering iframe from', id);
                                            existingIframe.remove();
                                        }
                                    }
                                });
                            }

                            function proceedWithSetup() {
                                const styles = useAutoStyle ? extractInputStyles() : customStyles;

                                console.log('Credit Card: Creating Hosted Fields instance');
                                braintree.hostedFields.create({
                                client: clientInstance,
                                styles: styles,
                                fields: {
                                    number: { selector: '#braintree_api-cc-number-hosted', placeholder: '4111 1111 1111 1111' },
                                    cvv: { selector: '#braintree_api-cc-cvv-hosted', placeholder: '123' },
                                    expirationDate: { selector: '#braintree_expiry-hosted', placeholder: 'MM/YY' }
                                }
                            }, function(hostedFieldsErr, hostedFieldsInstance) {
                                window.braintreeInitializing = false;
                                if (hostedFieldsErr) {
                                    console.error('Credit Card: Hosted Fields error:', hostedFieldsErr);
                                    handleInitializationFailure(hostedFieldsErr);
                                    return;
                                }
                                window.hf = hostedFieldsInstance;
                                hostedFieldsInstance.on('focus', function(event) {
                                    selectBraintreeRadio();
                                    const field = event.fields[event.emittedBy];
                                    const container = field.container;
                                    container.classList.add('braintree-hosted-fields-focused');
                                });
                                hostedFieldsInstance.on('blur', function(event) {
                                    const field = event.fields[event.emittedBy];
                                    const container = field.container;
                                    container.classList.remove('braintree-hosted-fields-focused');
                                });
                                ['braintree_api-cc-number-hosted', 'braintree_expiry-hosted', 'braintree_api-cc-cvv-hosted'].forEach(function(id) {
                                    const el = document.getElementById(id);
                                    if (el) {
                                        el.addEventListener('click', selectBraintreeRadio);
                                    }
                                });
                                hostedFieldsInstance.on('validityChange', function(event) {
                                    const fieldType = event.emittedBy;
                                    const field = event.fields[fieldType];
                                    const container = field.container;

                                    // Handle color classes
                                    container.classList.remove('valid', 'invalid', 'braintree-hosted-fields-valid', 'braintree-hosted-fields-invalid');
                                    if (field.isValid) {
                                        container.classList.add('valid', 'braintree-hosted-fields-valid');
                                        renderBraintreeFieldError(fieldType, '');
                                    } else if (!field.isPotentiallyValid) {
                                        container.classList.add('invalid', 'braintree-hosted-fields-invalid');
                                        let message = '';
                                        if (fieldType === 'number') message = 'Please enter a valid card number.';
                                        if (fieldType === 'cvv') message = 'Please enter a valid CVV.';
                                        if (fieldType === 'expirationDate') message = 'Please enter a valid expiration date.';
                                        renderBraintreeFieldError(fieldType, message);
                                    } else {
                                        renderBraintreeFieldError(fieldType, '');
                                    }
                                });
                                console.log('Credit Card: Hosted Fields ready');
                                braintreeInitializationComplete = true;
                            });
                            if (shouldUse3DS) {
                                console.log('Credit Card: 3DS enabled; creating 3DS instance');
                                braintree.threeDSecure.create({
                                    authorization: authorizationValue,
                                    version: 2
                                }, function(err, instance) {
                                    if (err && err.code === 'THREEDS_NOT_ENABLED_FOR_V2') {
                                        console.warn('Credit Card: 3DS2 not enabled, falling back to version 1');
                                        braintree.threeDSecure.create({
                                            authorization: authorizationValue,
                                            version: 1
                                        }, function(errV1, instanceV1) {
                                            if (errV1) {
                                                console.error('Credit Card: 3D Secure v1 fallback error:', errV1);
                                            } else {
                                                window.threeDS = instanceV1;
                                            }
                                        });
                                    } else if (err) {
                                        console.error('Credit Card: 3DS error (not recoverable):', err);
                                    } else {
                                        window.threeDS = instance;
                                    }
                                });
                            } else if (is3DSEnabled) {
                                console.warn('Credit Card: 3DS requested but skipped because only a tokenization key is available.');
                                console.log('Credit Card: Attempting to upgrade from tokenization key to client token...');
                                window.threeDS = null;
                                
                                // Attempt client-side upgrade from tokenization key to client token
                                attemptClientTokenUpgrade();
                            } else {
                                console.log('Credit Card: 3DS disabled via module settings; skipping 3DS resources');
                                window.threeDS = null;
                            }

                            window.braintreeHostedFieldsInitialized = true;
                            }

                            // Teardown existing instance if present
                            if (window.hf && typeof window.hf.teardown === 'function') {
                                console.log('Credit Card: Tearing down previous Hosted Fields instance');
                                try {
                                    const teardownResult = window.hf.teardown();
                                    if (teardownResult && typeof teardownResult.then === 'function') {
                                        // Wait for teardown to complete
                                        teardownResult
                                            .then(function() {
                                                console.log('Credit Card: Teardown completed successfully');
                                                window.hf = null;
                                                clearHostedFieldsIframes();
                                                proceedWithSetup();
                                            })
                                            .catch(function(err) {
                                                console.warn('Credit Card: Error tearing down previous Hosted Fields instance:', err);
                                                window.hf = null;
                                                clearHostedFieldsIframes();
                                                proceedWithSetup();
                                            });
                                    } else {
                                        // Teardown was synchronous or didn't return a promise
                                        window.hf = null;
                                        clearHostedFieldsIframes();
                                        proceedWithSetup();
                                    }
                                } catch (teardownErr) {
                                    console.warn('Credit Card: Error tearing down previous Hosted Fields instance:', teardownErr);
                                    window.hf = null;
                                    clearHostedFieldsIframes();
                                    proceedWithSetup();
                                }
                            } else {
                                // No existing instance, clear any lingering iframes and proceed
                                clearHostedFieldsIframes();
                                proceedWithSetup();
                            }
                        }

                        if (window.braintreeClientInstance && window.braintreeLastAuthorization === authorizationValue) {
                            console.log('Credit Card: Reusing cached Braintree client instance');
                            setupHostedFields(window.braintreeClientInstance);
                            return;
                        }

                        console.log('Credit Card: Creating new Braintree client');
                        braintree.client.create({authorization: authorizationValue}, function(clientErr, clientInstance) {
                            if (clientErr) {
                                console.error('Credit Card: Braintree client error:', clientErr);
                                handleInitializationFailure(clientErr);
                                return;
                            }

                            window.braintreeClientInstance = clientInstance;
                            window.braintreeLastAuthorization = authorizationValue;

                            setupHostedFields(clientInstance);
                        });
                    }

                    /**
                     * Attempt to upgrade from tokenization key to client token
                     * This provides a recovery path when server-side client token generation fails
                     * but 3DS is enabled and may be required by the customer's bank.
                     */
                    function attemptClientTokenUpgrade() {
                        if (!is3DSEnabled) {
                            console.log('Credit Card: 3DS not enabled, skipping upgrade attempt');
                            return;
                        }

                        const authorization = getAuthorizationDetails();
                        if (authorization.type === 'clientToken') {
                            console.log('Credit Card: Already using client token, no upgrade needed');
                            return;
                        }

                        console.log('Credit Card: Fetching client token for 3DS upgrade...');
                        
                        // Create an AJAX endpoint URL - this assumes the merchant has an endpoint
                        // If not available, this will fail gracefully
                        const upgradeUrl = window.location.pathname + '?main_page=ajax&action=braintree_get_client_token';
                        
                        fetch(upgradeUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        })
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error('Failed to fetch client token: HTTP ' + response.status);
                            }
                            return response.json();
                        })
                        .then(function(data) {
                            if (!data.clientToken) {
                                throw new Error('No client token in response');
                            }
                            
                            console.log('Credit Card: Client token received, upgrading Braintree instance...');
                            
                            // Update the hidden field
                            const tokenField = document.getElementById('braintree_client_token');
                            if (tokenField) {
                                tokenField.value = data.clientToken;
                            }
                            
                            // Teardown existing instances
                            teardownBraintreeInstances(function() {
                                // Reinitialize with the new client token
                                console.log('Credit Card: Reinitializing with client token for 3DS support');
                                initializeBraintreeHostedFields();
                            });
                        })
                        .catch(function(error) {
                            console.warn('Credit Card: Could not upgrade to client token:', error.message);
                            console.log('Credit Card: Continuing with tokenization key (3DS will not be available)');
                        });
                    }

                    let braintreeSubmissionInProgress = false;
                    let processingOverlayVisible = false;
                    let processingOverlayUsedOprc = false;
                    let braintreeInitializationComplete = false;

                    function forceOprcProcessingOverlayState(shouldShow) {
                        if (typeof document === 'undefined') {
                            return false;
                        }

                        var overlay = document.getElementById('oprc-processing-overlay');
                        if (!overlay) {
                            return false;
                        }

                        if (shouldShow) {
                            if (overlay._oprcManualHideTimeout) {
                                window.clearTimeout(overlay._oprcManualHideTimeout);
                                overlay._oprcManualHideTimeout = null;
                            }
                            if (overlay._oprcHideTimeout) {
                                window.clearTimeout(overlay._oprcHideTimeout);
                                overlay._oprcHideTimeout = null;
                            }
                            if (overlay._oprcHideHandler) {
                                overlay.removeEventListener('transitionend', overlay._oprcHideHandler);
                                overlay._oprcHideHandler = null;
                            }

                            overlay.setAttribute('aria-hidden', 'false');
                            overlay.style.display = 'flex';
                            overlay.getBoundingClientRect();
                            overlay.classList.add('is-active');
                            return true;
                        }

                        var finalizeHide = function() {
                            overlay.style.display = 'none';
                            overlay.setAttribute('aria-hidden', 'true');
                        };

                        if (!overlay.classList.contains('is-active')) {
                            finalizeHide();
                            return true;
                        }

                        var hideHandler = function(event) {
                            if (event && event.propertyName && event.propertyName !== 'opacity') {
                                return;
                            }

                            overlay.removeEventListener('transitionend', hideHandler);
                            finalizeHide();
                        };

                        if (overlay._oprcManualHideTimeout) {
                            window.clearTimeout(overlay._oprcManualHideTimeout);
                            overlay._oprcManualHideTimeout = null;
                        }

                        overlay.removeEventListener('transitionend', hideHandler);
                        overlay.addEventListener('transitionend', hideHandler);
                        overlay.classList.remove('is-active');

                        overlay._oprcManualHideTimeout = window.setTimeout(function() {
                            overlay.removeEventListener('transitionend', hideHandler);
                            finalizeHide();
                            overlay._oprcManualHideTimeout = null;
                        }, 250);

                        return true;
                    }

                    function showProcessingOverlay() {
                        var usedOprcOverlay = false;

                        if (typeof window !== 'undefined' && typeof window.oprcShowProcessingOverlay === 'function') {
                            window.oprcShowProcessingOverlay();
                            usedOprcOverlay = true;
                        }

                        if (!usedOprcOverlay) {
                            usedOprcOverlay = forceOprcProcessingOverlayState(true);
                        } else if (!forceOprcProcessingOverlayState(true)) {
                            usedOprcOverlay = false;
                        }

                        if (!usedOprcOverlay && typeof blockPage === 'function') {
                            blockPage(false, false);
                            processingOverlayVisible = true;
                            processingOverlayUsedOprc = false;
                            return;
                        }

                        if (usedOprcOverlay) {
                            processingOverlayVisible = true;
                            processingOverlayUsedOprc = true;
                        } else {
                            processingOverlayVisible = false;
                            processingOverlayUsedOprc = false;
                        }
                    }

                    function releaseFallbackOverlayAfterSuccess() {
                        if (processingOverlayVisible) {
                            if (!processingOverlayUsedOprc && typeof unblockPage === 'function') {
                                unblockPage();
                                processingOverlayVisible = false;
                                processingOverlayUsedOprc = false;
                            }
                        }
                    }

                    function hideProcessingOverlay() {
                        if (!processingOverlayVisible) {
                            return;
                        }

                        var handled = false;

                        if (processingOverlayUsedOprc && typeof window !== 'undefined' && typeof window.oprcHideProcessingOverlay === 'function') {
                            window.oprcHideProcessingOverlay();
                            handled = true;
                        }

                        if (!handled && processingOverlayUsedOprc) {
                            handled = forceOprcProcessingOverlayState(false);
                        }

                        if (!handled && typeof unblockPage === 'function') {
                            unblockPage();
                            handled = true;
                        }

                        if (handled) {
                            processingOverlayVisible = false;
                            processingOverlayUsedOprc = false;
                        }
                    }

                    document.addEventListener('DOMContentLoaded', function() {
                        console.log('Credit Card: Document ready; preparing Hosted Fields setup');
                        loadBraintreeHostedFieldsScripts(function () {
                            console.log('Credit Card: Hosted Fields scripts ready; initializing');
                            initializeBraintreeHostedFields();
                        });

                        if (typeof updateForm === 'function') {
                            const originalUpdateForm = updateForm;
                            updateForm = function () {
                                const shouldRefresh = window.oprcRefreshPayment === 'true';
                                if (shouldRefresh) {
                                    braintreeInitializationComplete = false;
                                    if (window.hf && typeof window.hf.teardown === 'function') {
                                        window.hf.teardown(function(teardownErr) {
                                            if (teardownErr) {
                                                console.error('Credit Card: Error tearing down Hosted Fields:', teardownErr);
                                            } else {
                                                console.log('Credit Card: Hosted Fields torn down successfully');
                                                window.hf = null;
                                            }
                                        });
                                    }

                                    if (window.threeDS && typeof window.threeDS.teardown === 'function') {
                                        window.threeDS.teardown(function(teardownErr) {
                                            if (teardownErr) {
                                                console.error('Credit Card: Error tearing down 3DS:', teardownErr);
                                            } else {
                                                console.log('Credit Card: 3DS torn down successfully');
                                                window.threeDS = null;
                                            }
                                        });
                                    }

                                    window.braintreeHostedFieldsInitialized = false;
                                }

                                originalUpdateForm.apply(this, arguments);

                                if (shouldRefresh) {
                                    setTimeout(() => initializeBraintreeHostedFields(), 250);
                                }
                            };
                        }

                        function showBraintreeSubmitMessage(message) {
                            let container = document.getElementById('braintree_submit_message');
                            if (!container) {
                                container = document.createElement('div');
                                container.id = 'braintree_submit_message';
                                container.className = 'braintree-field-error';

                                // For OPRC, place error at top of payment method section
                                const isOprc = (typeof window !== 'undefined' && typeof window.oprcShowProcessingOverlay === 'function');
                                let inserted = false;

                                if (isOprc) {
                                    // Try to insert at top of payment method section for OPRC
                                    const paymentMethodSection = document.getElementById('checkoutPayment') || 
                                                                 document.querySelector('.payment-method-section') ||
                                                                 document.querySelector('[id*=\"payment\"][id*=\"method\"]');
                                    if (paymentMethodSection) {
                                        paymentMethodSection.insertBefore(container, paymentMethodSection.firstChild);
                                        inserted = true;
                                    }
                                }

                                if (!inserted) {
                                    const paymentSubmit = document.getElementById('paymentSubmit');
                                    if (paymentSubmit) {
                                        paymentSubmit.insertBefore(container, paymentSubmit.firstChild);
                                    } else {
                                        const form = getCheckoutForm();
                                        if (form) form.insertBefore(container, form.firstChild);
                                    }
                                }
                            }

                            container.textContent = message || '';
                            container.style.display = message ? 'block' : 'none';
                        }

                        window.showBraintreeSubmitMessage = showBraintreeSubmitMessage;

                        // Register with OPRC's payment callback system instead of handling form submit directly
                        if (typeof window.oprcRegisterPaymentPreSubmitCallback === 'function') {
                            console.log('Credit Card: Registering with OPRC pre-submit callback system');
                            
                            window.oprcRegisterPaymentPreSubmitCallback(function(context) {
                                console.log('Credit Card: OPRC pre-submit callback invoked');
                                
                                const nonceField = document.getElementById('payment_method_nonce');
                                
                                // If we already have a nonce, continue immediately
                                if (nonceField && nonceField.value !== '') {
                                    showBraintreeSubmitMessage('');
                                    braintreeSubmissionInProgress = false;
                                    console.log('Credit Card: Nonce already present, continuing');
                                    context.resolve();
                                    return;
                                }
                                
                                // Check if submission is already in progress
                                if (braintreeSubmissionInProgress) {
                                    showBraintreeSubmitMessage('We\'re already processing your card details. Please wait.');
                                    console.log('Credit Card: Submission already in progress');
                                    context.reject(new Error('Submission already in progress'));
                                    return;
                                }
                                
                                // Authorize the card (this will handle 3DS if needed)
                                if (typeof authorizeCard === 'function') {
                                    braintreeSubmissionInProgress = true;
                                    showBraintreeSubmitMessage('');
                                    
                                    const checkoutForm = getCheckoutForm();
                                    const submitButton = checkoutForm ? checkoutForm.querySelector('#paymentSubmit input, #paymentSubmit button') : null;
                                    if (submitButton) submitButton.disabled = true;
                                    
                                    // Store the context so authorizeCard can resolve/reject when done
                                    window.braintreeOprcContext = context;
                                    
                                    console.log('Credit Card: Starting card authorization');
                                    authorizeCard();
                                    
                                    // Return false to indicate async handling - context will be resolved/rejected when 3DS completes
                                    return false;
                                } else {
                                    showBraintreeSubmitMessage('Please enter your card details before continuing.');
                                    console.error('Credit Card: authorizeCard function not available');
                                    context.reject(new Error('Card authorization not available'));
                                }
                            }, 'braintree_api');
                        } else {
                            console.warn('Credit Card: OPRC callback system not available, falling back to form submit handler');
                            
                            // Fallback for non-OPRC checkouts
                            const checkoutForm = getCheckoutForm();
                            if (checkoutForm) {
                                checkoutForm.addEventListener('submit', function(e) {
                                    const selectedPayment = document.querySelector('[name=payment]:checked');
                                    const nonceField = document.getElementById('payment_method_nonce');

                                    if (selectedPayment && selectedPayment.value === 'braintree_api') {
                                        if (nonceField && nonceField.value !== '') {
                                            showBraintreeSubmitMessage('');
                                            braintreeSubmissionInProgress = false;
                                            return true;
                                        }

                                        e.preventDefault();

                                        showProcessingOverlay();

                                        if (braintreeSubmissionInProgress) {
                                            showBraintreeSubmitMessage('We\'re already processing your card details. Please wait.');
                                            return false;
                                        }

                                        if (typeof authorizeCard === 'function') {
                                            braintreeSubmissionInProgress = true;
                                            showBraintreeSubmitMessage('');
                                            const submitButton = checkoutForm.querySelector('#paymentSubmit input, #paymentSubmit button');
                                            if (submitButton) submitButton.disabled = true;
                                            authorizeCard();
                                        } else {
                                            showBraintreeSubmitMessage('Please enter your card details before continuing.');
                                            hideProcessingOverlay();
                                        }

                                        return false;
                                    }
                                });
                            }
                        }
                    });

                    document.addEventListener('onePageCheckoutReloaded', function () {
                        console.log('Credit Card: onePageCheckoutReloaded detected — reinitializing');
                        window.braintreeHostedFieldsInitialized = false;
                        braintreeInitializationComplete = false;
                        loadBraintreeHostedFieldsScripts(function () {
                            console.log('Credit Card: Hosted Fields scripts ready after reload; initializing');
                            initializeBraintreeHostedFields();
                        });
                    });

                    let teardownPending = false;

                    window.authorizeCard = function() {
                        // Check if Braintree Hosted Fields are initialized before attempting tokenization
                        if (!braintreeInitializationComplete) {
                            console.warn('Credit Card: Hosted Fields not yet initialized. Waiting for initialization to complete...');
                            showBraintreeSubmitMessage('Payment form is still loading. Please wait a moment and try again.');
                            hideProcessingOverlay();
                            setLoading('reset');
                            braintreeSubmissionInProgress = false;
                            
                            // Reject OPRC context if available
                            if (window.braintreeOprcContext && typeof window.braintreeOprcContext.reject === 'function') {
                                window.braintreeOprcContext.reject(new Error('Hosted Fields not yet initialized'));
                                window.braintreeOprcContext = null;
                            }
                            return;
                        }

                        // Guard: Ensure we have a client token when 3DS is enabled
                        if (is3DSEnabled) {
                            const currentAuth = getAuthorizationDetails();
                            if (currentAuth.type !== 'clientToken') {
                                console.error('Credit Card: 3DS is enabled but client token is not available');
                                hideProcessingOverlay();
                                setLoading('reset');
                                braintreeSubmissionInProgress = false;
                                showBraintreeSubmitMessage('3D Secure authentication is required but currently unavailable. Please refresh this page and try again, or select a different payment method.');
                                
                                // Re-enable submit button
                                const checkoutForm = getCheckoutForm();
                                const submitButton = checkoutForm ? checkoutForm.querySelector('#paymentSubmit input, #paymentSubmit button') : null;
                                if (submitButton) submitButton.disabled = false;
                                
                                // Reject OPRC context if available
                                if (window.braintreeOprcContext && typeof window.braintreeOprcContext.reject === 'function') {
                                    window.braintreeOprcContext.reject(new Error('3DS enabled but client token unavailable'));
                                    window.braintreeOprcContext = null;
                                }
                                return;
                            }
                        }

                        braintreeSubmissionInProgress = true;
                        
                        // Always ensure processing overlay is visible during tokenization
                        // Only manually show overlay if not in OPRC context - OPRC manages its own overlay
                        if (!window.braintreeOprcContext) {
                            showProcessingOverlay();
                        }
                        
                        setLoading('loading');
                        teardownPending = false;

                        const hostedFieldsInstance = window.hf;
                        if (!hostedFieldsInstance || typeof hostedFieldsInstance.tokenize !== 'function') {
                            console.error('Credit Card: Hosted Fields instance missing or invalid');
                            hideProcessingOverlay();
                            setLoading('reset');
                            braintreeSubmissionInProgress = false;
                            showBraintreeSubmitMessage('We could not verify your card details. Please refresh the page and try again.');
                            
                            // Reject OPRC context if available
                            if (window.braintreeOprcContext && typeof window.braintreeOprcContext.reject === 'function') {
                                window.braintreeOprcContext.reject(new Error('Hosted Fields instance missing or invalid'));
                                window.braintreeOprcContext = null;
                            }
                            return;
                        }

                        // Force browser to paint the overlay before starting heavy tokenization work
                        // Use double requestAnimationFrame to ensure at least one paint cycle occurs
                        function startBraintreeTokenization() {
                            hostedFieldsInstance.tokenize().then(function(payload) {
                            const threeDSInstance = window.threeDS;
                            if (threeDSInstance && typeof threeDSInstance.verifyCard === 'function') {
                                // Ensure processing overlay is visible during 3DS lookup
                                // Only manually show overlay if not in OPRC context - OPRC manages its own overlay
                                if (!window.braintreeOprcContext) {
                                    console.log('Credit Card: Ensuring processing overlay is visible for 3DS verification');
                                    showProcessingOverlay();
                                } else {
                                    console.log('Credit Card: OPRC context detected - overlay already managed by OPRC');
                                }
                                
                                return threeDSInstance.verifyCard({
                                    amount: '" . number_format((float)$order->info['total'], 2, '.', '') . "',
                                    nonce: payload.nonce,
                                    bin: payload.details.bin,
                                    email: '" . $order->customer['email_address'] . "',
                                    billingAddress: {
                                        givenName: \"" . addslashes($order->billing['firstname']) . "\",
                                        surname: \"" . addslashes($order->billing['lastname']) . "\",
                                        phoneNumber: \"" . addslashes($order->customer['telephone']) . "\",
                                        streetAddress: \"" . addslashes($order->billing['street_address']) . "\",
                                        locality: \"" . addslashes($order->billing['city']) . "\",
                                        region: \"" . addslashes($order->billing['state']) . "\",
                                        postalCode: \"" . addslashes($order->billing['postcode']) . "\",
                                        countryCodeAlpha2: \"" . addslashes($order->billing['country']['iso_code_2']) . "\"
                                    },
                                    onLookupComplete: function(data, next) {
                                        console.log('Credit Card: 3DS lookup complete', data);

                                        // IMPORTANT: drop the OPRC overlay so the shopper can interact with the 3DS modal
                                        try {
                                            if (typeof window.oprcHideProcessingOverlay === 'function') {
                                                window.oprcHideProcessingOverlay();
                                            } else if (typeof hideProcessingOverlay === 'function') {
                                                hideProcessingOverlay();
                                            }
                                        } catch (e) {
                                            console.warn('Credit Card: could not hide overlay before 3DS challenge', e);
                                        }

                                        // Proceed to present challenge (if required)
                                        next();
                                    }
                                }).then(function(result) {
                                    console.log('Credit Card: Full verifyCard result', result);
                                    // bring back the spinner now that the modal is gone
                                    // Always show the overlay after 3DS completes, regardless of OPRC context
                                    // because we hid it earlier in onLookupComplete
                                    console.log('Credit Card: Re-showing processing overlay after 3DS completion');
                                    showProcessingOverlay();

                                    // Normalize helpers
                                    var tds = (result && (result.threeDSecureInfo || result.three_d_secure_info)) || {};
                                    var status = tds.status || result.status || '';
                                    var shifted = (typeof result.liabilityShifted !== 'undefined') ? result.liabilityShifted
                                                                : (typeof tds.liabilityShifted !== 'undefined') ? tds.liabilityShifted
                                                                : false;

                                    var successStatuses = [
                                        'authenticate_successful',
                                        'attempt_successful',
                                        'challenge_completed',
                                        'successful' // some gateways collapse to this
                                    ];

                                    var statusLooksGood = status && successStatuses.indexOf(String(status).toLowerCase()) !== -1;

                                    if (result && result.nonce) {
                                        document.getElementById('payment_method_nonce').value = result.nonce;
                                        setLoading('success');
                                        releaseFallbackOverlayAfterSuccess();
                                        braintreeSubmissionInProgress = false;
                                        showBraintreeSubmitMessage('');
                                        console.log('Credit Card: 3DS verification complete with nonce');
                                        
                                        // Resolve OPRC context if available, otherwise submit form directly
                                        if (window.braintreeOprcContext && typeof window.braintreeOprcContext.resolve === 'function') {
                                            console.log('Credit Card: Resolving OPRC context, OPRC will continue submission');
                                            window.braintreeOprcContext.resolve();
                                            window.braintreeOprcContext = null;
                                        } else {
                                            console.log('Credit Card: No OPRC context, submitting form directly');
                                            resubmitCheckoutForm();
                                        }
                                    } else if (shifted || statusLooksGood) {
                                        // 3DS succeeded but nonce not bubbled up: keep using the original tokenized nonce
                                        console.log('Credit Card: 3DS success without new nonce; using original hosted-fields nonce');
                                        document.getElementById('payment_method_nonce').value = payload.nonce;
                                        setLoading('success');
                                        releaseFallbackOverlayAfterSuccess();
                                        braintreeSubmissionInProgress = false;
                                        showBraintreeSubmitMessage('');
                                        
                                        // Resolve OPRC context if available, otherwise submit form directly
                                        if (window.braintreeOprcContext && typeof window.braintreeOprcContext.resolve === 'function') {
                                            console.log('Credit Card: Resolving OPRC context, OPRC will continue submission');
                                            window.braintreeOprcContext.resolve();
                                            window.braintreeOprcContext = null;
                                        } else {
                                            console.log('Credit Card: No OPRC context, submitting form directly');
                                            resubmitCheckoutForm();
                                        }
                                    } else {
                                        console.warn('Credit Card: 3DS canceled or failed; no usable nonce');
                                        handle3DSFailure();
                                    }
                                }).catch(function(err) {
                                    console.error('Credit Card: 3DS error (challenge failed or canceled):', err);
                                    handle3DSFailure();
                                });
                            } else {
                                // 3DS is disabled, use payload nonce directly
                                console.log('Credit Card: 3DS disabled — using tokenized nonce', payload.nonce);
                                document.getElementById('payment_method_nonce').value = payload.nonce;
                                setLoading('success');
                                releaseFallbackOverlayAfterSuccess();
                                braintreeSubmissionInProgress = false;
                                showBraintreeSubmitMessage('');
                                
                                // Resolve OPRC context if available, otherwise submit form directly
                                if (window.braintreeOprcContext && typeof window.braintreeOprcContext.resolve === 'function') {
                                    console.log('Credit Card: Resolving OPRC context, OPRC will continue submission');
                                    window.braintreeOprcContext.resolve();
                                    window.braintreeOprcContext = null;
                                } else {
                                    console.log('Credit Card: No OPRC context, submitting form directly');
                                    resubmitCheckoutForm();
                                }
                            }
                        }).catch(function(err) {
                            console.error('Credit Card: Braintree tokenization error:', err);

                            hideProcessingOverlay();
                            setLoading('reset');
                            braintreeSubmissionInProgress = false;
                            showBraintreeSubmitMessage('We could not verify your card details. Please review the highlighted fields and try again.');

                            if (err.code === 'HOSTED_FIELDS_FIELDS_INVALID') {
                                const state = hostedFieldsInstance.getState();
                                const invalidKeys = err.details.invalidFieldKeys || [];

                                invalidKeys.forEach(function(fieldKey) {
                                    const container = state.fields[fieldKey]?.container;
                                    if (container) container.classList.add('invalid');

                                    let message = '';
                                    if (fieldKey === 'number') message = 'Please enter a valid card number.';
                                    if (fieldKey === 'cvv') message = 'Please enter a valid CVV.';
                                    if (fieldKey === 'expirationDate') message = 'Please enter a valid expiration date.';

                                    renderBraintreeFieldError(fieldKey, message);
                                });
                            }
                            
                            // Reject OPRC context if available
                            if (window.braintreeOprcContext && typeof window.braintreeOprcContext.reject === 'function') {
                                console.log('Credit Card: Rejecting OPRC context due to tokenization error');
                                window.braintreeOprcContext.reject(err);
                                window.braintreeOprcContext = null;
                            }
                        });
                        }

                        // Use double requestAnimationFrame to ensure browser paints the overlay
                        // before starting heavy tokenization and 3DS verification work
                        if (window.requestAnimationFrame) {
                            console.log('Credit Card: Using requestAnimationFrame to ensure overlay is painted');
                            requestAnimationFrame(function() {
                                requestAnimationFrame(function() {
                                    startBraintreeTokenization();
                                });
                            });
                        } else {
                            // Fallback for older browsers
                            console.log('Credit Card: Using setTimeout fallback for overlay paint');
                            setTimeout(startBraintreeTokenization, 0);
                        }
                    };

                    function getCheckoutForm() {
                        return document.querySelector('form[name=\"checkout_payment\"]');
                    }

                    function resubmitCheckoutForm() {
                        const form = getCheckoutForm();
                        if (!form) {
                            console.error('Credit Card: Checkout form not found');
                            return;
                        }

                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                            return;
                        }

                        const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                        if (form.dispatchEvent(submitEvent)) {
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    }

                    function handle3DSFailure() {
                        hideProcessingOverlay();
                        setLoading('reset');

                        // Clear the nonce so the user can retry
                        const nonceField = document.getElementById('payment_method_nonce');
                        if (nonceField) {
                            nonceField.value = '';
                        }

                        if (!teardownPending) {
                            teardownPending = true;
                            braintreeInitializationComplete = false;
                            teardownBraintreeInstances(function() {
                                // Reinitialize after teardown completes
                                console.log('Credit Card: Reinitializing Hosted Fields after teardown due to failure');
                                if (typeof initializeBraintreeHostedFields === 'function') {
                                    initializeBraintreeHostedFields();
                                }
                            });
                        }

                        //alert('Authentication was canceled or failed. Please try again.');

                        const verifyBtn = document.querySelector('#paymentSubmit input');
                        if (verifyBtn) {
                            verifyBtn.disabled = false;
                            verifyBtn.value = 'Continue';
                        }
                        braintreeSubmissionInProgress = false;
                        showBraintreeSubmitMessage('Authentication was canceled or failed. Please try again.');
                        
                        // Reject OPRC context if available
                        if (window.braintreeOprcContext && typeof window.braintreeOprcContext.reject === 'function') {
                            console.log('Credit Card: Rejecting OPRC context due to 3DS failure');
                            window.braintreeOprcContext.reject(new Error('3DS authentication failed or was canceled'));
                            window.braintreeOprcContext = null;
                        }
                    }

                    function teardownBraintreeInstances(callback) {
                        if (!window.braintreeHostedFieldsInitialized) {
                            console.log('Credit Card: Teardown skipped — already torn down');
                            if (typeof callback === 'function') callback();
                            return;
                        }

                        braintreeInitializationComplete = false;
                        let hfDone = false;
                        let threeDSDone = false;

                        function checkDone() {
                            if (hfDone && threeDSDone && typeof callback === 'function') {
                                callback();
                            }
                        }

                        if (window.hf && typeof window.hf.teardown === 'function') {
                            window.hf.teardown(function(teardownErr) {
                                if (teardownErr) {
                                    console.error('Credit Card: Error tearing down Hosted Fields:', teardownErr);
                                } else {
                                    console.log('Credit Card: Hosted Fields torn down successfully');
                                    window.hf = null;
                                }
                                hfDone = true;
                                checkDone();
                            });
                        } else {
                            hfDone = true;
                        }

                        if (window.threeDS && typeof window.threeDS.teardown === 'function') {
                            window.threeDS.teardown(function(teardownErr) {
                                if (teardownErr) {
                                    console.error('Credit Card: Error tearing down 3DS:', teardownErr);
                                } else {
                                    console.log('Credit Card: 3DS torn down successfully');
                                    window.threeDS = null;
                                }
                                threeDSDone = true;
                                checkDone();
                            });
                        } else {
                            threeDSDone = true;
                        }

                        window.braintreeHostedFieldsInitialized = false;
                    }

                    function setLoading(type) {
                        const verifyBtn = document.querySelector('#paymentSubmit input');
                        if (!verifyBtn) return;
                        if (type === 'loading') {
                            verifyBtn.value = 'Processing Card...';
                            verifyBtn.disabled = true;
                        } else if (type === 'reset') {
                            verifyBtn.value = 'Continue';
                            verifyBtn.disabled = false;
                        } else if (type === 'success') {
                            verifyBtn.value = 'Card Authorized!';
                            verifyBtn.disabled = true;
                        }
                    }
                })();

                function renderBraintreeFieldError(fieldKey, message) {
                    const container = document.getElementById('braintree_error_' + fieldKey);
                    if (!container) return;

                    if (!message) {
                        container.innerHTML = '';
                        return;
                    }

                    container.innerHTML =
                        '<span>' +
                            '<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"20px\" height=\"20px\" viewBox=\"0 0 24 24\" fill=\"none\">' +
                                '<path fill-rule=\"evenodd\" clip-rule=\"evenodd\"' +
                                    ' d=\"M19.5 12C19.5 16.1421 16.1421 19.5 12 19.5C7.85786 19.5 4.5 16.1421 4.5 12C4.5 7.85786 7.85786 4.5 12 4.5C16.1421 4.5 19.5 7.85786 19.5 12Z' +
                                    'M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z' +
                                    'M11.25 13.5V8.25H12.75V13.5H11.25ZM11.25 15.75V14.25H12.75V15.75H11.25Z\"' +
                                    ' fill=\"#EE1B23\"></path>' +
                            '</svg>' +
                            '<i class=\"error\">' + message + '</i>' +
                        '</span>';
                }
                </script>

                <script>
                    function braintreeCheck() {
                        const selectedPayment = document.querySelector('[name=payment]:checked');
                        const nonceField = document.getElementById('payment_method_nonce');

                        if (selectedPayment && selectedPayment.value === 'braintree_api') {
                            if (!nonceField || nonceField.value === '') {
                                // If OPRC callback system is registered, let it handle authorization
                                // Otherwise, handle it here for non-OPRC checkouts
                                if (typeof window.oprcRegisterPaymentPreSubmitCallback === 'function') {
                                    console.log('Credit Card: OPRC callback system detected, deferring to pre-submit handler');
                                    // Return true to allow OPRC to proceed with its callback system
                                    // The registered callback will handle authorization
                                    return true;
                                } else {
                                    console.log('Credit Card: No nonce yet — authorizing card');
                                    // Show processing overlay immediately before authorizing
                                    if (typeof window.oprcShowProcessingOverlay === 'function') {
                                        window.oprcShowProcessingOverlay();
                                    }
                                    authorizeCard(); // This handles everything, including submitting the form
                                    return false; // prevent default submission
                                }
                            }
                        }

                        return true; // Nonce already present or different payment method
                    }
                </script>";
        
        // Add the combined hidden field as the last item in the array
        $fieldsArray[] = [
            'title' => '',
            'field' => $hiddenFieldContent
        ];

        return [
            'id'     => $this->code,
            'module' => MODULE_PAYMENT_BRAINTREE_TEXT_TITLE,
            'fields' => $fieldsArray
        ];
    }

    /**
     * Validate input before order confirmation.
     */
    function pre_confirmation_check() {
        global $messageStack;
        if (empty($_POST['payment_method_nonce'])) {
            // Use checkout_payment for OPRC, otherwise use checkout for default Zen Cart
            $message_location = (defined('OPRC_STATUS') && OPRC_STATUS == 'true') ? 'checkout_payment' : 'checkout';
            $messageStack->add_session($message_location, 'Your payment was not processed. Please authorize your card and try again.', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
        if (isset($_POST['bt_payment_type'])) {
            $_SESSION['bt_payment_type'] = $_POST['bt_payment_type'];
        }
        $this->nonce = $_SESSION['payment_method_nonce'] = $_POST['payment_method_nonce'];
    }

    /**
     * Build confirmation details.
     */
    function confirmation() {
        $confirmation = ['title' => '', 'fields' => []];
        if (!empty($_POST['braintree_cc_firstname'])) {
            $confirmation['fields'][] = [
                'title' => MODULE_PAYMENT_BRAINTREE_TEXT_CREDIT_CARD_FIRSTNAME,
                'field' => $_POST['braintree_cc_firstname']
            ];
        }
        // Additional confirmation fields as needed...
        return $confirmation;
    }

    /**
     * Prepare hidden fields for the checkout confirmation page.
     */
    function process_button() {
        global $order;
        $process_button_string  = "\n" . zen_draw_hidden_field('payment_method_nonce', $_POST['payment_method_nonce']);
        return $process_button_string;
    }

    /**
     * Ajax version of process_button.
     */
    function process_button_ajax() {
        global $order;
        $processButton = [
            'ccFields' => [
                'payment_method_nonce' => 'payment_method_nonce'
            ],
            'extraFields' => [zen_session_name() => zen_session_id()]
        ];
        return $processButton;
    }

    /**
     * Validate the Braintree response and complete the payment.
     */
    function before_process() {
        return $this->braintreeCommon->before_process_common($this->merchantAccountID, array(), (MODULE_PAYMENT_BRAINTREE_SETTLEMENT == 'true'));
    }

    /**
     * After the transaction is processed, update order status history and store transaction details.
     * Uses the Pending status if the payment was not captured.
     */
    public function after_process() {
            global $insert_id, $db, $order;

            // Retrieve transaction details from session
            $txnId = $_SESSION['braintree_transaction_id'] ?? '';
            $paymentStatus = $_SESSION['braintree_payment_status'] ?? 'Pending';
            $cardType = $_SESSION['braintree_card_type'] ?? 'Unknown';
            $currency = $_SESSION['braintree_currency'] ?? '';
            $amount = $_SESSION['braintree_amount'] ?? 0;

            // Determine order status based on transaction settlement
            $orderStatus = (MODULE_PAYMENT_BRAINTREE_SETTLEMENT == 'true')
                    ? MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID
                    : MODULE_PAYMENT_BRAINTREE_ORDER_PENDING_STATUS_ID;

            // Workaround for OPRC and other checkout processes that don't properly set $insert_id:
            // Try to retrieve the order ID from the database if it's not set or invalid
            if ((empty($insert_id) || (int)$insert_id <= 0) && !empty($order->info['order_id'])) {
                    $insert_id = $order->info['order_id'];
            }
            
            // If still not set, try to get the most recent order for this customer
            if ((empty($insert_id) || (int)$insert_id <= 0) && !empty($_SESSION['customer_id'])) {
                    $result = $db->Execute("SELECT orders_id FROM " . TABLE_ORDERS . "
                                            WHERE customers_id = " . (int)$_SESSION['customer_id'] . "
                                            ORDER BY orders_id DESC LIMIT 1");
                    if (!$result->EOF) {
                            $insert_id = $result->fields['orders_id'];
                            error_log("Braintree API: Retrieved order ID $insert_id from database (global \$insert_id was not set by checkout process).");
                    }
            }

            // Validate that we have both a valid order ID and transaction ID
            if ($txnId && !empty($insert_id) && (int)$insert_id > 0) {
                    // Update order status in Zen Cart
                    $db->Execute("UPDATE " . TABLE_ORDERS . "
                                                SET orders_status = " . (int)$orderStatus . "
                                                WHERE orders_id = " . (int)$insert_id);

                    // Insert into order status history
                    $sql_data_array = [
                            'orders_id'         => (int)$insert_id,
                            'orders_status_id'  => (int)$orderStatus,
                            'date_added'        => 'now()',
                            'comments'          => "Braintree Transaction ID: " . $txnId,
                            'customer_notified' => 1
                    ];
                    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

                    // Insert transaction details into Braintree table
                    $braintree_order = [
                            'order_id'          => (int)$insert_id,
                            'txn_id'            => $txnId,
                            'txn_type'          => 'sale',
                            'module_name'       => 'braintree_api',
                            'payment_type'      => $cardType,
                            'payment_status'    => $paymentStatus,
                            'first_name'        => $order->billing['firstname'] ?? '',
                            'last_name'         => $order->billing['lastname'] ?? '',
                            'payer_business_name' => $order->billing['company'] ?? '',
                            'address_name'      => ($order->billing['firstname'] ?? '') . ' ' . ($order->billing['lastname'] ?? ''),
                            'address_street'    => $order->billing['street_address'] ?? '',
                            'address_city'      => $order->billing['city'] ?? '',
                            'address_state'     => $order->billing['state'] ?? '',
                            'address_zip'       => $order->billing['postcode'] ?? '',
                            'address_country'   => $order->billing['country']['title'] ?? ($order->billing['country'] ?? ''),
                            'payer_email'       => $order->customer['email_address'] ?? '',
                            'payment_date'      => date('Y-m-d'),
                            'settle_amount'     => (float)$amount,
                            'settle_currency'   => $currency,
                            'date_added'        => date('Y-m-d'),
                            'module_mode'       => 'braintree_api'
                    ];
                    zen_db_perform(TABLE_BRAINTREE, $braintree_order);
            } else {
                    // Log the error with appropriate detail
                    if (empty($insert_id) || (int)$insert_id <= 0) {
                            error_log("Braintree API Error: Invalid or missing order ID (insert_id: " . var_export($insert_id, true) . "). Transaction ID: " . ($txnId ?: 'N/A') . ".");
                    } elseif (empty($txnId)) {
                            error_log("Braintree API Error: Missing transaction ID for order ID: $insert_id.");
                    }
            }

            // Cleanup session variables
            unset($_SESSION['braintree_transaction_id']);
            unset($_SESSION['braintree_payment_status']);
            unset($_SESSION['braintree_card_type']);
            unset($_SESSION['braintree_currency']);
            unset($_SESSION['braintree_amount']);
    }

    function admin_notification($zf_order_id) {
        if (!defined('MODULE_PAYMENT_BRAINTREE_STATUS')) {
            return '';
        }
        global $db;
        $module = $this->code;
        $output = '';
        // Retrieve transaction details via the common class delegation.
        $response = $this->_GetTransactionDetails($zf_order_id);
        // If an admin notification template exists, include it.
        if (file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_admin_notification.php')) {
            include_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/braintree/braintree_admin_notification.php');
        }
        return $output;
    }

    /**
     * Delegate transaction detail lookup to the common class.
     */
    function getTransactionId($orderId) {
        return $this->braintreeCommon->getTransactionId($orderId);
    }

    function _GetTransactionDetails($oID) {
        return $this->braintreeCommon->_GetTransactionDetails($oID);
    }

    /**
     * Delegate refund processing to the common class.
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
            MODULE_PAYMENT_BRAINTREE_ORDER_STATUS, // Use the module-specific paid status configuration
            $this->code
        );
    }

    function check() {
            global $db;
            if (!isset($this->_check)) {
                    $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_STATUS'");
                    $this->_check = $check_query->RecordCount();
            }
            return $this->_check;
    }

    /**
     * Installation, upgrade, configuration keys, and removal methods remain largely unchanged.
     */
    function install() {
        global $db, $messageStack;

        // DO NOT EDIT BELOW, Instead add a new version file to YOUR_ADMIN/includes/installers/braintree/ and use $db to add or change the configuration
        // Ensure the Braintree transactions table exists via the common class.
        $this->braintreeCommon->create_braintree_table();
        $this->notify('NOTIFY_PAYMENT_BRAINTREE_INSTALLED');
    }

    function keys() {
        return [
            'MODULE_PAYMENT_BRAINTREE_STATUS',
            'MODULE_PAYMENT_BRAINTREE_VERSION',
            'MODULE_PAYMENT_BRAINTREE_MERCHANTID',
            'MODULE_PAYMENT_BRAINTREE_PUBLICKEY',
            'MODULE_PAYMENT_BRAINTREE_PRIVATEKEY',
            'MODULE_PAYMENT_BRAINTREE_TOKENIZATION_KEY',
            'MODULE_PAYMENT_BRAINTREE_CURRENCY',
            'MODULE_PAYMENT_BRAINTREE_MERCHANT_ACCOUNT_ID',
            'MODULE_PAYMENT_BRAINTREE_USE_3DS',
            'MODULE_PAYMENT_BRAINTREE_ORDER_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_ORDER_PENDING_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_REFUNDED_STATUS_ID',
            'MODULE_PAYMENT_BRAINTREE_SERVER',
            'MODULE_PAYMENT_BRAINTREE_AUTOMATE_STYLING',
            'MODULE_PAYMENT_BRAINTREE_CUSTOM_FIELD_STYLE',
            'MODULE_PAYMENT_BRAINTREE_HOSTED_IFRAME_CSS',
            'MODULE_PAYMENT_BRAINTREE_TOTAL_SELECTOR',
            'MODULE_PAYMENT_BRAINTREE_SETTLEMENT',
            'MODULE_PAYMENT_BRAINTREE_DEBUGGING',
            'MODULE_PAYMENT_BRAINTREE_TIMEOUT',
            'MODULE_PAYMENT_BRAINTREE_SORT_ORDER',
            'MODULE_PAYMENT_BRAINTREE_ZONE'
        ];
    }

    function remove() {
        global $db;

        $configurationKeys = $this->keys();
        if (!empty($configurationKeys)) {
            $sanitizedKeys = array_map('zen_db_input', $configurationKeys);

            $db->Execute(
                "DELETE FROM " . TABLE_CONFIGURATION .
                " WHERE configuration_key IN ('" . implode("','", $sanitizedKeys) . "')"
            );
        }

        $this->notify('NOTIFY_PAYMENT_BRAINTREE_UNINSTALLED');
    }

    /**
     * Calculate order amount based on currency.
     */
    function calc_order_amount($amount, $braintreeCurrency, $applyFormatting = false) {
        global $currencies;
        $amount = ($amount * $currencies->get_value($braintreeCurrency));
        if ($braintreeCurrency == 'JPY' || (int)$currencies->get_decimal_places($braintreeCurrency) == 0) {
            $amount = (int)$amount;
            $applyFormatting = false;
        }
        return round($amount, 2);
    }

    /**
     * Return the Braintree Gateway via the common class.
     * (Deprecated – use $this->braintreeCommon->get_braintree_gateway() instead.)
     */
    function gateway() {
        return $this->braintreeCommon->get_braintree_gateway();
    }

    /**
     * Check if a key exists in an array.
     */
    private function checkGetValue($array, $prop_name) {
        if (!is_array($array)) {
            throw new Exception("Key array is expected");
        }
        if (!is_string($prop_name) || empty($prop_name)) {
            throw new Exception("Invalid property name type");
        }
        if (!isset($array[$prop_name])) {
            throw new Exception("Property $prop_name does not exist");
        }
        return $array[$prop_name];
    }

    /**
     * Determine if an upgrade is available by comparing the latest installer version
     * with the currently installed version stored in configuration.
     *
     * @param string|null $latestInstallerVersion
     * @return bool
     */
    private function isUpgradeAvailable($latestInstallerVersion) {
        if ($latestInstallerVersion === null) {
            return false;
        }

        if (defined('MODULE_PAYMENT_BRAINTREE_VERSION')) {
            $currentVersion = MODULE_PAYMENT_BRAINTREE_VERSION;
        } elseif (defined('MODULE_PAYMENT_BRAINTREE_STATUS')) {
            $currentVersion = '0.0.0';
        } else {
            return false;
        }

        return version_compare($latestInstallerVersion, $currentVersion, '>');
    }

    /**
     * Locate the highest versioned installer available in the admin installers directory.
     *
     * @return string|null
     */
    private function getLatestInstallerVersion() {
        if (!defined('DIR_FS_ADMIN')) {
            return null;
        }

        $installerDirectory = DIR_FS_ADMIN . 'includes/installers/braintree';
        if (!is_dir($installerDirectory) || !is_readable($installerDirectory)) {
            return null;
        }

        $installers = scandir($installerDirectory);
        if (!is_array($installers)) {
            return null;
        }

        $installers = array_filter($installers, function ($file) {
            return preg_match('/^\d+(?:_\d+)*\.php$/', $file);
        });

        if (empty($installers)) {
            return null;
        }

        usort($installers, function ($a, $b) {
            return version_compare(str_replace('_', '.', basename($a, '.php')), str_replace('_', '.', basename($b, '.php')));
        });

        $latest = end($installers);
        if ($latest === false) {
            return null;
        }

        return str_replace('_', '.', basename($latest, '.php'));
    }

    /**
     * Build the HTML for the upgrade button that posts back to the modules page
     * to trigger the installer logic.
     *
     * @param string $latestInstallerVersion
     * @return string
     */
    private function buildUpgradeButton($latestInstallerVersion) {
        $actionFile = defined('FILENAME_MODULES') ? FILENAME_MODULES : 'modules.php';

        $form = zen_draw_form(
            'braintreeUpgrade',
            $actionFile,
            'set=payment&module=' . $this->code . '&action=upgrade',
            'post',
            'class="braintree-upgrade-form" style="display:inline-block;margin-left:0.5rem;"',
            true
        );

        $form .= zen_draw_hidden_field('module', $this->code);

        if (isset($_SESSION['securityToken'])) {
            $form .= zen_draw_hidden_field('securityToken', $_SESSION['securityToken']);
        }

        $form .= '<button type="submit" class="btn btn-warning">' . sprintf(MODULE_PAYMENT_BRAINTREE_UPGRADE_BUTTON_TEXT, htmlspecialchars($latestInstallerVersion, ENT_QUOTES, 'UTF-8')) . '</button>';
        $form .= '</form>';

        return $form;
    }
}

/**
 * Backwards compatibility for ZC versions prior to v1.5.2.
 */
if (!function_exists('plugin_version_check_for_updates')) {
    function plugin_version_check_for_updates($fileid = 0, $version_string_to_check = '') {
        if ($fileid == 0)
            return FALSE;
        $new_version_available = FALSE;
        $lookup_index = 0;
        $url = 'http://www.zen-cart.com/downloads.php?do=versioncheck&id=' . (int)$fileid;
        $data = json_decode(file_get_contents($url), true);
        if (strcmp($data[$lookup_index]['latest_plugin_version'], $version_string_to_check) > 0)
            $new_version_available = TRUE;
        if (!in_array('v' . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR, $data[$lookup_index]['zcversions']))
            $new_version_available = FALSE;
        return ($new_version_available) ? $data[$lookup_index] : FALSE;
    }
}