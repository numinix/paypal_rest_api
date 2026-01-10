<?php
/**
 * tpl_modules_paypalr_product_googlepay.php
 * Google Pay button template for product page
 * 
 * Conditionally uses either:
 * - PayPal SDK (when user is logged in) - no email needed
 * - Native Google Pay SDK (when user is not logged in but has Google Merchant ID) - captures email
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr_googlepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalr_googlepay.php');

    $googlePayModule = new paypalr_googlepay();
    
    // Get wallet configuration to verify Google Pay is enabled
    $walletConfig = $googlePayModule->ajaxGetWalletConfig();
    if (empty($walletConfig['success']) || empty($walletConfig['clientId'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    // Determine which SDK to use based on login status and merchant ID
    $userLoggedIn = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0;
    $googleMerchantId = $walletConfig['googleMerchantId'] ?? '';
    $hasMerchantId = ($googleMerchantId !== '');
    
    // Use native Google Pay SDK only when user is not logged in AND merchant ID is set
    $useNativeGooglePay = (!$userLoggedIn && $hasMerchantId);
?>

<?php if ($useNativeGooglePay): ?>
    <?php
        // Native Google Pay SDK implementation - captures email address
        // Only used when user is not logged in and Google Merchant ID is configured
        
        // Load Google Pay API script
        $googlePayEnvironment = $walletConfig['googlePayEnvironment'] ?? 'TEST';
        echo '<script src="https://pay.google.com/gp/p/js/pay.js" async></script>';
        
        // Build configuration for native implementation
        $clientId = $walletConfig['clientId'];
        $currency = $_SESSION['currency'] ?? DEFAULT_CURRENCY;
        $environment = $walletConfig['environment'] ?? 'sandbox';
        $productId = (int)$_GET['products_id'];
        $initialTotal = number_format($currencies->value(zen_get_products_base_price($productId)), 2, '.', '');
        
        // Use store country code from parent template (already queried in tpl_paypalr_product_info.php)
        // No need to query database again since $storeCountryCode is already available
        
        // Load the native Google Pay JavaScript integration
        // Use product-specific constant to avoid conflicts with checkout
        if (!defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_NATIVE_PRODUCT_JS_LOADED')) {
            define('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_NATIVE_PRODUCT_JS_LOADED', true);
            $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.googlepay.native.js';
            if (file_exists($scriptPath)) {
                echo '<script>' . file_get_contents($scriptPath) . '</script>';
            } else {
                error_log('Google Pay Error: jquery.paypalr.googlepay.native.js not found at: ' . $scriptPath);
                return;
            }
        }
    ?>
    
    <script>
    "use strict";
    
    // Configuration for native Google Pay integration
    window.paypalrGooglePayConfig = {
        environment: "<?php echo $googlePayEnvironment; ?>",
        googleMerchantId: "<?php echo addslashes($googleMerchantId); ?>",
        currencyCode: "<?php echo $currency; ?>",
        initialTotal: "<?php echo $initialTotal; ?>",
        storeName: "<?php echo addslashes(STORE_NAME); ?>",
        storeCountryCode: "<?php echo $storeCountryCode; ?>",
        sessionAppend: "<?php echo zen_session_id() ? ('?' . zen_session_name() . '=' . zen_session_id()) : ''; ?>",
        context: 'product',
        productId: <?php echo $productId; ?>
    };
    
    // Initialize Google Pay when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.initPayPalRGooglePay === 'function') {
                window.initPayPalRGooglePay();
            }
        });
    } else {
        if (typeof window.initPayPalRGooglePay === 'function') {
            window.initPayPalRGooglePay();
        }
    }
    </script>
<?php else: ?>
    <?php
        // PayPal SDK Google Pay implementation - used when user is logged in
        // This uses paypal.Googlepay().config() to get proper tokenization specification
        // The JS file handles all SDK loading and initialization internally
        //
        // Use product-specific constant to avoid conflicts with checkout
        if (!defined('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_PRODUCT_JS_LOADED')) {
            define('MODULE_PAYMENT_PAYPALR_GOOGLEPAY_PRODUCT_JS_LOADED', true);
            
            $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.googlepay.js';
            if (file_exists($scriptPath)) {
                echo '<script>' . file_get_contents($scriptPath) . '</script>';
            } else {
                error_log('Google Pay Error: jquery.paypalr.googlepay.js not found at: ' . $scriptPath);
                return;
            }
        }
    ?>
<?php endif; ?>

<!-- Google Pay Button Container -->
<div id="paypalr-googlepay-button" class="paypalr-googlepay-button"></div>

<style>
#paypalr-googlepay-button {
    min-height: 50px;
    margin-top: 20px;
}

@media (max-width:768px) {
    #paypalr-googlepay-button {
        width: 100% !important;
    }
}

body.processing-payment {
    cursor: wait;
}
</style>
