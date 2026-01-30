<?php
declare(strict_types=1);

/**
 * Test to verify stored_credential is properly structured for recurring payments
 *
 * This test ensures that:
 * 1. stored_credential is at the correct level in the card payment source (not nested in attributes)
 * 2. payment_type is set to RECURRING for subscription payments
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Stored Credential Structure Test...\n\n");

$basePath = dirname(__DIR__);

// Test 1: Verify stored_credential is not nested in attributes
fwrite(STDOUT, "Test 1: Checking stored_credential structure in build_vault_payment_source...\n");
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that stored_credential is NOT in attributes (old buggy way)
    if (preg_match('/\$cardPayload\[\'attributes\'\]\[\'stored_credential\'\]/', $content)) {
        fwrite(STDERR, "✗ stored_credential is incorrectly nested in attributes (will cause PayPal API errors)\n");
        exit(1);
    }
    
    // Check that stored_credential is directly on cardPayload (correct way)
    if (preg_match('/\$cardPayload\[\'stored_credential\'\]\s*=\s*\$storedDefaults/', $content)) {
        fwrite(STDOUT, "✓ stored_credential is correctly at top level of card payload\n");
    } else {
        fwrite(STDERR, "✗ stored_credential assignment not found in expected format\n");
        exit(1);
    }
    
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

// Test 2: Verify payment_type is set to RECURRING for recurring payments
fwrite(STDOUT, "Test 2: Checking payment_type for recurring payments...\n");
// Reuse $content from Test 1
if (strpos($content, "'payment_type' => 'RECURRING'") !== false) {
    fwrite(STDOUT, "✓ payment_type correctly set to RECURRING for subscription payments\n");
} else {
    fwrite(STDERR, "✗ RECURRING payment_type not found for subscription payments\n");
    exit(1);
}

fwrite(STDOUT, "\n");

fwrite(STDOUT, "All tests passed! ✓\n");
fwrite(STDOUT, "\nVerified:\n");
fwrite(STDOUT, "1. stored_credential is properly structured at card payload level (not in attributes)\n");
fwrite(STDOUT, "2. payment_type is set to RECURRING for subscription payments\n");
