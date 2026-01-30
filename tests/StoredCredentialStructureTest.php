<?php
declare(strict_types=1);

/**
 * Test to verify stored_credential is properly structured for recurring payments
 *
 * This test ensures that:
 * 1. stored_credential is at the correct level in the card payment source
 * 2. Not nested inside 'attributes' key
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running Stored Credential Structure Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify stored_credential is not nested in attributes
echo "Test 1: Checking stored_credential structure in build_vault_payment_source...\n";
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that stored_credential is NOT in attributes (old buggy way)
    if (preg_match('/\$cardPayload\[\'attributes\'\]\[\'stored_credential\'\]/', $content)) {
        echo "✗ stored_credential is incorrectly nested in attributes (will cause PayPal API errors)\n";
        exit(1);
    }
    
    // Check that stored_credential is directly on cardPayload (correct way)
    if (preg_match('/\$cardPayload\[\'stored_credential\'\]\s*=\s*\$storedDefaults/', $content)) {
        echo "✓ stored_credential is correctly at top level of card payload\n";
    } else {
        echo "✗ stored_credential assignment not found in expected format\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 2: Verify payment_type is set to RECURRING for recurring payments
echo "Test 2: Checking payment_type for recurring payments...\n";
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that RECURRING is used for stored_credential payment_type
    if (strpos($content, "'payment_type' => 'RECURRING'") !== false) {
        echo "✓ payment_type correctly set to RECURRING for subscription payments\n";
    } else {
        echo "✗ RECURRING payment_type not found for subscription payments\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

echo "All tests passed! ✓\n";
echo "\nVerified:\n";
echo "1. stored_credential is properly structured at card payload level (not in attributes)\n";
echo "2. payment_type is set to RECURRING for subscription payments\n";
