<?php
/**
 * Manual verification script to demonstrate the Logger::logJSON fix
 * 
 * This script shows that after the fix, Logger::logJSON no longer modifies
 * the original data array when masking sensitive information.
 * 
 * Run with: php tests/manual_verification_logger_fix.php
 */

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_FS_LOGS')) {
    define('DIR_FS_LOGS', sys_get_temp_dir());
}
if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', false);
}

require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Logger.php';

use PayPalAdvancedCheckout\Common\Logger;

echo "=================================================================\n";
echo "Logger::logJSON() Fix Verification\n";
echo "=================================================================\n\n";

echo "Bug Description:\n";
echo "----------------\n";
echo "Logger::logJSON was modifying the original data array by reference,\n";
echo "truncating credit card numbers to only the last 4 digits.\n";
echo "This caused PayPal API to reject orders with UNPROCESSABLE_ENTITY.\n\n";

echo "Testing the fix...\n\n";

// Create a sample PayPal order request with a full credit card number
$orderRequest = [
    'intent' => 'CAPTURE',
    'payment_source' => [
        'card' => [
            'name' => 'John Doe',
            'number' => '4532015112830366',  // Full 16-digit test card number
            'security_code' => '123',
            'expiry' => '2028-12',
            'billing_address' => [
                'address_line_1' => '123 Main St',
                'admin_area_2' => 'San Jose',
                'admin_area_1' => 'CA',
                'postal_code' => '95131',
                'country_code' => 'US',
            ],
        ],
    ],
    'purchase_units' => [
        [
            'amount' => [
                'currency_code' => 'USD',
                'value' => '100.00',
            ],
        ],
    ],
];

echo "BEFORE calling Logger::logJSON():\n";
echo "  Card Number: " . $orderRequest['payment_source']['card']['number'] . "\n";
echo "  Security Code: " . $orderRequest['payment_source']['card']['security_code'] . "\n\n";

// Call logJSON (this would have corrupted the data before the fix)
$logOutput = Logger::logJSON($orderRequest, false, false);

echo "AFTER calling Logger::logJSON():\n";
echo "  Card Number: " . $orderRequest['payment_source']['card']['number'] . "\n";
echo "  Security Code: " . $orderRequest['payment_source']['card']['security_code'] . "\n\n";

// Parse the log output to show what was logged
$loggedData = json_decode($logOutput, true);

echo "What gets LOGGED (masked for security):\n";
echo "  Card Number: " . $loggedData['payment_source']['card']['number'] . "\n";
echo "  Security Code: " . $loggedData['payment_source']['card']['security_code'] . "\n\n";

// Verify the fix
$originalNumberIntact = ($orderRequest['payment_source']['card']['number'] === '4532015112830366');
$originalSecurityCodeIntact = ($orderRequest['payment_source']['card']['security_code'] === '123');
$loggedNumberMasked = ($loggedData['payment_source']['card']['number'] === '0366');
$loggedSecurityCodeMasked = ($loggedData['payment_source']['card']['security_code'] === '***');

echo "=================================================================\n";
echo "Verification Results:\n";
echo "=================================================================\n";
echo "✓ Original card number preserved (16 digits): " . ($originalNumberIntact ? "PASS" : "FAIL") . "\n";
echo "✓ Original security code preserved: " . ($originalSecurityCodeIntact ? "PASS" : "FAIL") . "\n";
echo "✓ Logged card number masked (last 4 only): " . ($loggedNumberMasked ? "PASS" : "FAIL") . "\n";
echo "✓ Logged security code masked: " . ($loggedSecurityCodeMasked ? "PASS" : "FAIL") . "\n\n";

if ($originalNumberIntact && $originalSecurityCodeIntact && $loggedNumberMasked && $loggedSecurityCodeMasked) {
    echo "✅ ALL CHECKS PASSED - Fix is working correctly!\n\n";
    echo "Impact: Credit card payments will now work because the full card\n";
    echo "        number is preserved when sending to PayPal's API.\n";
    exit(0);
} else {
    echo "❌ SOME CHECKS FAILED - Fix may not be working correctly!\n";
    exit(1);
}
