<?php
/**
 * tpl_modules_paypalr_applepay.php
 * Apple Pay button template for shopping cart page
 * 
 * Conditionally uses either:
 * - PayPal SDK (when user is logged in) - no email needed
 * - Native ApplePaySession SDK (when user is not logged in) - captures email
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr_applepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_applepay.php');

    $applePayModule = new paypalr_applepay();
    
    // Get wallet configuration  
    $walletConfig = $applePayModule->ajaxGetWalletConfig();
    if (empty($walletConfig['success']) || empty($walletConfig['clientId'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    // Determine which SDK to use based on login status
    $userLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
    
    // Use native Apple Pay SDK only when user is not logged in (to capture email)
    $useNativeApplePay = !$userLoggedIn;
    
    $clientId = $walletConfig['clientId'];
    $merchantId = $walletConfig['merchantId'] ?? '';
    $intent = $walletConfig['intent'] ?? 'capture';
    $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
    $environment = $walletConfig['environment'] ?? 'sandbox';
    $initialTotal = number_format($currencies->value($_SESSION['cart']->total), 2, '.', '');
    
    // Get store country code for Apple Pay
    $country_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = " . (int)STORE_COUNTRY;
    $country_result = $db->Execute($country_query);
    $storeCountryCode = $country_result->fields['countries_iso_code_2'] ?? 'US';
?>

<?php if ($useNativeApplePay): ?>
    <?php
        // Native Apple Pay SDK implementation - captures email address
        // Only used when user is not logged in
        
        // Build PayPal SDK URL - still needed for order creation/capture
        $sdkComponents = 'buttons,googlepay,applepay';
        $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($clientId);
        $sdkUrl .= '&components=' . urlencode($sdkComponents);
        $sdkUrl .= '&currency=' . urlencode($currency);
        
        // Add buyer-country for sandbox mode (required for testing)
        if ($environment === 'sandbox') {
            $sdkUrl .= '&buyer-country=US';
        }
    ?>
    
    <script src="<?php echo $sdkUrl; ?>"></script>
    
    <?php
        // Load the native Apple Pay JavaScript integration
        $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.applepay.native.js';
        if (file_exists($scriptPath)) {
            echo '<script>' . file_get_contents($scriptPath) . '</script>';
        } else {
            // Fallback to original implementation if native version doesn't exist
            $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.applepay.js';
            if (file_exists($scriptPath)) {
                echo '<script>' . file_get_contents($scriptPath) . '</script>';
            }
        }
    ?>
    
    <script>
    "use strict";
    
    // Configuration for native Apple Pay integration
    window.paypalrApplePayConfig = {
        clientId: "<?php echo addslashes($clientId); ?>",
        merchantId: "<?php echo addslashes($merchantId); ?>",
        storeCountryCode: "<?php echo $storeCountryCode; ?>",
        currencyCode: "<?php echo $currency; ?>",
        initialTotal: "<?php echo $initialTotal; ?>",
        storeName: "<?php echo addslashes(STORE_NAME); ?>",
        environment: "<?php echo $environment; ?>",
        sessionAppend: "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>",
        context: 'cart'
    };
    
    // Initialize Apple Pay when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initPayPalRApplePay === 'function') {
                window.initPayPalRApplePay();
            }
        });
    } else {
        if (typeof window.initPayPalRApplePay === 'function') {
            window.initPayPalRApplePay();
        }
    }
    </script>
<?php else: ?>
    <?php
        // PayPal SDK Apple Pay implementation - used when user is logged in
        // The JS file handles all SDK loading and initialization internally
        
        $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.applepay.js';
        if (file_exists($scriptPath)) {
            echo '<script>' . file_get_contents($scriptPath) . '</script>';
        } else {
            error_log('Apple Pay Error: jquery.paypalr.applepay.js not found at: ' . $scriptPath);
            return;
        }
    ?>
<?php endif; ?>

<!-- Apple Pay Button Container -->
<div id="paypalr-applepay-button" class="paypalr-applepay-button"></div>
<div id="paypalr-applepay-error" style="display:none; color:red; margin-top:10px;"></div>

<style>
#paypalr-applepay-button {
    min-height: 50px;
    margin-top: 20px;
    margin-left: auto !important;
    width: 228px;
}

.paypalr-applepay-button {
    width: 100%;
    max-width: 320px;
}

@media (max-width:768px) {
    #paypalr-applepay-button {
        width: 100% !important;
    }
}

body.processing-payment {
    cursor: wait;
}
</style>
