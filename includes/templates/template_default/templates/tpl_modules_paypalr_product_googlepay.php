<?php
/**
 * tpl_modules_paypalr_product_googlepay.php
 * Google Pay button template for product page
 * 
 * Uses PayPal SDK's paypal.Googlepay() API for proper tokenization.
 * The SDK provides the correct tokenization specification and handles
 * Google Pay integration through PayPal's REST API.
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
    
    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0;
    
    // If user is not logged in and guest wallet is disabled, don't show the button
    if (!$isLoggedIn && !empty($walletConfig['enableGuestWallet']) && $walletConfig['enableGuestWallet'] === false) {
        // Guest wallet is disabled and user is not logged in - don't render button
        return;
    }
?>

<script>
"use strict";

// Check if customer is logged in to determine which SDK approach to use
// IMPORTANT: This must be set BEFORE loading jquery.paypalr.googlepay.wallet.js
// because the wallet script reads this value immediately when it loads (IIFE execution)
window.paypalrWalletIsLoggedIn = <?php echo (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) ? 'true' : 'false'; ?>;
</script>

<?php
    // Load the PayPal SDK Google Pay JavaScript integration for product/cart pages
    // This uses paypal.Googlepay().config() to get proper tokenization specification
    // The JS file handles all SDK loading and initialization internally
    $scriptPath = DIR_WS_MODULES . 'payment/paypal/PayPalRestful/jquery.paypalr.googlepay.wallet.js';
    if (file_exists($scriptPath)) {
        echo '<script>' . file_get_contents($scriptPath) . '</script>';
    } else {
        // Log error if the required JavaScript file is missing
        error_log('Google Pay Error: jquery.paypalr.googlepay.wallet.js not found at: ' . $scriptPath);
        return;
    }
?>

<script>
"use strict";

// Initialize Google Pay button when DOM is ready
// The PayPal SDK implementation handles all initialization internally
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.paypalrGooglePayRender === 'function') {
            window.paypalrGooglePayRender();
        }
    });
} else {
    if (typeof window.paypalrGooglePayRender === 'function') {
        window.paypalrGooglePayRender();
    }
}
</script>

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
