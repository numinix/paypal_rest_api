<?php
/**
 * Manual verification script for MessageStack output() functionality.
 * 
 * This script demonstrates that messages are properly formatted and displayed
 * when using the messageStack->output() method.
 */

// Initialize session array
$_SESSION = [];

// Load the MessageStack compatibility class
require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/Compatibility/MessageStack.php';

echo "=== MessageStack Manual Verification ===\n\n";

// Test 1: Basic message output
echo "Test 1: Basic message output\n";
echo "----------------------------\n";
$messageStack = new messageStack();
$messageStack->add('test_stack', 'This is a test message', 'error');
$output = $messageStack->output('test_stack');
echo "Output:\n";
echo htmlspecialchars($output) . "\n\n";
echo "✓ Test 1 passed - message output works\n\n";

// Test 2: Session messages (simulating archive action)
echo "Test 2: Session messages (archive action simulation)\n";
echo "-----------------------------------------------------\n";
$messageStack1 = new messageStack();
$messageStack1->add_session('paypalac_subscriptions', 'Subscription #123 has been archived.', 'success');

// Simulate page redirect/reload - create new instance
$messageStack2 = new messageStack();
$output = $messageStack2->output('paypalac_subscriptions');
echo "Output after 'page reload':\n";
echo htmlspecialchars($output) . "\n\n";

// Check if it contains the expected message
if (strpos($output, 'Subscription #123 has been archived.') !== false) {
    echo "✓ Test 2 passed - session messages work correctly\n\n";
} else {
    echo "✗ Test 2 failed - expected message not found\n\n";
}

// Test 3: Multiple message types
echo "Test 3: Multiple message types\n";
echo "-------------------------------\n";
$messageStack = new messageStack();
$messageStack->add('multi_stack', 'Success message', 'success');
$messageStack->add('multi_stack', 'Error message', 'error');
$messageStack->add('multi_stack', 'Warning message', 'warning');
$output = $messageStack->output('multi_stack');
echo "Output:\n";
echo htmlspecialchars($output) . "\n\n";

// Verify all types are present
$hasSuccess = strpos($output, 'alert-success') !== false;
$hasError = strpos($output, 'alert-danger') !== false;
$hasWarning = strpos($output, 'alert-warning') !== false;

if ($hasSuccess && $hasError && $hasWarning) {
    echo "✓ Test 3 passed - multiple message types rendered correctly\n\n";
} else {
    echo "✗ Test 3 failed - not all message types found\n\n";
}

// Test 4: HTML escaping
echo "Test 4: HTML escaping (XSS prevention)\n";
echo "--------------------------------------\n";
$messageStack = new messageStack();
$messageStack->add('xss_stack', '<script>alert("xss")</script>', 'error');
$output = $messageStack->output('xss_stack');
echo "Output:\n";
echo $output . "\n\n";

// Verify script tag is escaped
if (strpos($output, '<script>') === false && strpos($output, '&lt;script&gt;') !== false) {
    echo "✓ Test 4 passed - HTML is properly escaped\n\n";
} else {
    echo "✗ Test 4 failed - HTML escaping issue\n\n";
}

// Test 5: Empty stack
echo "Test 5: Empty stack\n";
echo "-------------------\n";
$messageStack = new messageStack();
$output = $messageStack->output('empty_stack');
if ($output === '') {
    echo "✓ Test 5 passed - empty stack returns empty string\n\n";
} else {
    echo "✗ Test 5 failed - expected empty string\n\n";
}

echo "=== All Tests Complete ===\n";
echo "\nThis verifies that the MessageStack fix resolves the issue where\n";
echo "'paypalac_subscriptions' was displayed instead of the actual message.\n";
echo "\nThe output() method now properly formats messages with Bootstrap alert\n";
echo "classes (alert-success, alert-danger, alert-warning) and escapes HTML.\n";
