<?php
/**
 * tpl_modules_paypalac_product_googlepay.php
 * Google Pay button template for product page
 * 
 * Uses PayPal SDK's paypal.Googlepay() API for proper tokenization.
 * The SDK provides the correct tokenization specification and handles
 * Google Pay integration through PayPal's REST API.
 */
    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalac_googlepay.php');
    require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac_googlepay.php');

    $googlePayModule = new paypalac_googlepay();
    
    // Get wallet configuration to verify Google Pay is enabled
    // Pass product ID so shipping requirement is based on the viewed product's virtual status, not the cart contents
    $walletConfig = $googlePayModule->ajaxGetWalletConfig((int)$_GET['products_id']);
    if (empty($walletConfig['success']) || empty($walletConfig['clientId'])) {
        // If configuration fails, don't show the button
        return;
    }
    
    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0;
    $guestWalletEnabled = isset($walletConfig['enableGuestWallet']) && $walletConfig['enableGuestWallet'] === true;
    $productRequiresShipping = !empty($walletConfig['cartRequiresShipping']);
    
    // Show Google Pay button if:
    // 1. User is logged in (uses native Google Pay SDK without callbackIntents), OR
    // 2. Guest wallet is enabled (uses native Google Pay SDK with callbackIntents for shipping), OR
    // 3. Product is virtual (no shipping needed - works for all users without callbackIntents)
    if (!$isLoggedIn && !$guestWalletEnabled && $productRequiresShipping) {
        // User not logged in AND guest wallet disabled AND physical product - don't show button
        return;
    }
    
    // For logged-in users: Native Google Pay SDK without callbackIntents (avoids OR_BIBED_06 errors)
    // For guests (when guest wallet enabled): Native Google Pay SDK with callbackIntents for shipping calculation
?>

<script>
"use strict";

// Check if customer is logged in to determine which SDK approach to use
// IMPORTANT: This must be set BEFORE loading jquery.paypalac.googlepay.wallet.js
// because the wallet script reads this value immediately when it loads (IIFE execution)
window.paypalacWalletIsLoggedIn = <?php echo (isset($_SESSION['customer_id']) && $_SESSION['customer_id'] > 0) ? 'true' : 'false'; ?>;

// Product page configuration - provide the product price so shipping quote
// requests can include it in the total (the product isn't in the cart yet)
window.paypalacGooglePayConfig = {
    initialTotal: "<?php echo number_format($currencies->value(zen_get_products_base_price((int)$_GET['products_id'])), 2, '.', ''); ?>",
    productId: <?php echo (int)$_GET['products_id']; ?>
};
</script>

<?php
    // Load the PayPal SDK Google Pay JavaScript integration for product/cart pages
    // This uses paypal.Googlepay().config() to get proper tokenization specification
    // The JS file handles all SDK loading and initialization internally
    $scriptPath = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.googlepay.wallet.js';
    if (file_exists($scriptPath)) {
        echo '<script>' . file_get_contents($scriptPath) . '</script>';
    } else {
        // Log error if the required JavaScript file is missing
        error_log('Google Pay Error: jquery.paypalac.googlepay.wallet.js not found at: ' . $scriptPath);
        return;
    }
?>

<script>
"use strict";

// Initialize Google Pay button when DOM is ready
// The PayPal SDK implementation handles all initialization internally
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.paypalacGooglePayRender === 'function') {
            window.paypalacGooglePayRender();
        }
    });
} else {
    if (typeof window.paypalacGooglePayRender === 'function') {
        window.paypalacGooglePayRender();
    }
}
</script>

<!-- Google Pay Button Container -->
<div id="paypalac-googlepay-button" class="paypalac-googlepay-button"></div>

<style>
#paypalac-googlepay-button {
    min-height: 50px;
    margin-top: 20px;
}

@media (max-width:768px) {
    #paypalac-googlepay-button {
        width: 100% !important;
    }
}

body.processing-payment {
    cursor: wait;
}
</style>
