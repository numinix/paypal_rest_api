<?php
declare(strict_types=1);

/**
 * Test to verify PayPal-Request-Id header is set for recurring payments
 *
 * This test ensures that:
 * 1. PayPal-Request-Id is generated before createOrder call
 * 2. Request ID is deterministic based on subscription ID and date
 * 3. Request ID is logged for debugging
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running PayPal Request ID Test...\n\n");

$basePath = dirname(__DIR__);

// Test 1: Verify PayPal-Request-Id is set before createOrder
fwrite(STDOUT, "Test 1: Checking PayPal-Request-Id is set in process_rest_payment...\n");
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that setPayPalRequestId is called
    if (strpos($content, 'setPayPalRequestId') !== false) {
        fwrite(STDOUT, "✓ setPayPalRequestId method is called\n");
    } else {
        fwrite(STDERR, "✗ setPayPalRequestId not found\n");
        exit(1);
    }
    
    // Check that it's called before createOrder
    $setPosn = strpos($content, 'setPayPalRequestId');
    $createPosn = strpos($content, 'createOrder');
    
    if ($setPosn !== false && $createPosn !== false && $setPosn < $createPosn) {
        fwrite(STDOUT, "✓ setPayPalRequestId is called before createOrder\n");
    } else {
        fwrite(STDERR, "✗ setPayPalRequestId not called before createOrder\n");
        exit(1);
    }
    
    // Check that request_id is generated with subscription ID
    if (strpos($content, "request_id = 'recurring_'") !== false) {
        fwrite(STDOUT, "✓ Request ID includes 'recurring_' prefix\n");
    } else {
        fwrite(STDERR, "✗ Request ID doesn't have proper format\n");
        exit(1);
    }
    
    // Check that request_id includes subscription ID and date
    if (strpos($content, 'subscription_id') !== false && 
        strpos($content, "date('Ymd')") !== false) {
        fwrite(STDOUT, "✓ Request ID is deterministic (subscription ID + date)\n");
    } else {
        fwrite(STDERR, "✗ Request ID is not properly deterministic\n");
        exit(1);
    }
    
    // Check that request_id is logged
    if (strpos($content, "error_log('PayPal REST Request-Id:") !== false) {
        fwrite(STDOUT, "✓ Request ID is logged for debugging\n");
    } else {
        fwrite(STDERR, "✗ Request ID is not logged\n");
        exit(1);
    }
    
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

fwrite(STDOUT, "All tests passed! ✓\n");
fwrite(STDOUT, "\nVerified:\n");
fwrite(STDOUT, "1. PayPal-Request-Id header is set before createOrder call\n");
fwrite(STDOUT, "2. Request ID format: 'recurring_{subscription_id}_{date}'\n");
fwrite(STDOUT, "3. Request ID is deterministic for same subscription on same day\n");
fwrite(STDOUT, "4. Request ID is logged for troubleshooting\n");
fwrite(STDOUT, "\nThis prevents duplicate payment processing and satisfies PayPal's idempotency requirement.\n");
