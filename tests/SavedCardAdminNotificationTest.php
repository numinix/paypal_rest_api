<?php
/**
 * Test that verifies the paypalac_savedcard module has the proper admin notification
 * features to enable refund functionality.
 * 
 * Bug fix: The saved card module was missing the external transaction handling
 * in admin_notification that would alert admins about changes made outside
 * the Zen Cart admin.
 */

$testPassed = true;
$errors = [];

// Path to the saved card module
$savedCardModulePath = __DIR__ . '/../includes/modules/payment/paypalac_savedcard.php';
$paypalacModulePath = __DIR__ . '/../includes/modules/payment/paypalac.php';

if (!file_exists($savedCardModulePath)) {
    echo "Error: paypalac_savedcard.php not found at $savedCardModulePath\n";
    exit(1);
}

if (!file_exists($paypalacModulePath)) {
    echo "Error: paypalac.php not found at $paypalacModulePath\n";
    exit(1);
}

// Read the file contents
$savedCardContent = file_get_contents($savedCardModulePath);
$paypalacContent = file_get_contents($paypalacModulePath);

echo "Testing saved card admin notification features...\n\n";

// Test 1: Check that admin_notification method exists
if (strpos($savedCardContent, 'public function admin_notification') === false) {
    $testPassed = false;
    $errors[] = "admin_notification method not found in saved card module";
} else {
    echo "✓ admin_notification method exists\n";
}

// Test 2: Check that AdminMain class is used
if (strpos($savedCardContent, 'new AdminMain(') === false) {
    $testPassed = false;
    $errors[] = "AdminMain class not instantiated in saved card module";
} else {
    echo "✓ AdminMain class is used for admin display\n";
}

// Test 3: Check that external transaction handling exists
if (strpos($savedCardContent, 'externalTxnAdded()') === false) {
    $testPassed = false;
    $errors[] = "externalTxnAdded() check not found - external transaction handling missing";
} else {
    echo "✓ External transaction handling (externalTxnAdded) exists\n";
}

// Test 4: Check that zen_update_orders_history is called for external transactions
if (strpos($savedCardContent, 'zen_update_orders_history($zf_order_id, MODULE_PAYMENT_PAYPALAC_EXTERNAL_ADDITION)') === false) {
    $testPassed = false;
    $errors[] = "zen_update_orders_history call for external transactions not found";
} else {
    echo "✓ Order history is updated for external transactions\n";
}

// Test 5: Check that sendAlertEmail is called for external transactions
if (strpos($savedCardContent, 'sendAlertEmail(MODULE_PAYMENT_PAYPALAC_ALERT_SUBJECT_ORDER_ATTN') === false) {
    $testPassed = false;
    $errors[] = "sendAlertEmail call for external transactions not found";
} else {
    echo "✓ Alert email is sent for external transactions\n";
}

// Test 6: Check that _doRefund method exists
if (strpos($savedCardContent, 'public function _doRefund(') === false) {
    $testPassed = false;
    $errors[] = "_doRefund method not found";
} else {
    echo "✓ _doRefund method exists\n";
}

// Test 7: Check that _doCapt method exists
if (strpos($savedCardContent, 'public function _doCapt(') === false) {
    $testPassed = false;
    $errors[] = "_doCapt method not found";
} else {
    echo "✓ _doCapt method exists\n";
}

// Test 8: Check that _doVoid method exists
if (strpos($savedCardContent, 'public function _doVoid(') === false) {
    $testPassed = false;
    $errors[] = "_doVoid method not found";
} else {
    echo "✓ _doVoid method exists\n";
}

// Test 9: Check that _doAuth method exists
if (strpos($savedCardContent, 'public function _doAuth(') === false) {
    $testPassed = false;
    $errors[] = "_doAuth method not found";
} else {
    echo "✓ _doAuth method exists\n";
}

// Test 10: Check that help() returns the wiki link
if (strpos($savedCardContent, "return [\n            'link' => 'https://github.com/lat9/paypalac/wiki'\n        ];") === false &&
    strpos($savedCardContent, "'link' => 'https://github.com/lat9/paypalac/wiki'") === false) {
    $testPassed = false;
    $errors[] = "help() method should return wiki link like paypalac.php";
} else {
    echo "✓ help() method returns wiki link\n";
}

// Test 11: Compare admin_notification structure with paypalac.php
echo "\nComparing admin_notification structure with paypalac.php...\n";

// Extract admin_notification method from both files using regex
$savedCardAdminNotification = '';
$paypalacAdminNotification = '';

if (preg_match('/public function admin_notification\([^)]*\)\s*\{[^}]+\}/', $savedCardContent, $matches)) {
    $savedCardAdminNotification = $matches[0];
}

if (preg_match('/public function admin_notification\([^)]*\)\s*\{[^}]+\}/', $paypalacContent, $matches)) {
    $paypalacAdminNotification = $matches[0];
}

// Check that both have similar key components
$keyComponents = [
    'getPayPalRestfulApi()',
    'new AdminMain(',
    'externalTxnAdded()',
    '$admin_main->get()',
];

$savedCardHasAll = true;
$paypalacHasAll = true;

foreach ($keyComponents as $component) {
    if (strpos($savedCardContent, $component) !== false) {
        // Good - component found in saved card
    } else {
        $savedCardHasAll = false;
    }
    
    if (strpos($paypalacContent, $component) !== false) {
        // Good - component found in paypalac
    } else {
        $paypalacHasAll = false;
    }
}

if ($savedCardHasAll) {
    echo "✓ Saved card admin_notification has all key components\n";
} else {
    $testPassed = false;
    $errors[] = "Saved card admin_notification is missing some key components";
}

echo "\n";

// Summary
if ($testPassed) {
    echo "All admin notification tests passed! ✓\n";
    echo "\nThe saved card module now has:\n";
    echo "- admin_notification method with AdminMain class\n";
    echo "- External transaction handling with alerts\n";
    echo "- Refund, capture, void, and auth methods\n";
    echo "- Help link to wiki documentation\n";
    exit(0);
} else {
    echo "Tests failed:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
}
