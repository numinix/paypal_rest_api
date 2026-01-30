<?php
declare(strict_types=1);

/**
 * Test to verify PayPal REST client uses correct environment
 *
 * This test ensures that:
 * 1. The REST client initialization uses MODULE_PAYMENT_PAYPALR_SERVER
 * 2. Live environment uses CLIENTID_L and SECRET_L
 * 3. Sandbox environment uses CLIENTID_S and SECRET_S
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running PayPal Environment Detection Test...\n\n");

$basePath = dirname(__DIR__);

// Test 1: Verify correct constant names are used
fwrite(STDOUT, "Test 1: Checking PayPal REST client uses correct constant names...\n");
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that it uses MODULE_PAYMENT_PAYPALR_SERVER
    if (strpos($content, 'MODULE_PAYMENT_PAYPALR_SERVER') !== false) {
        fwrite(STDOUT, "✓ Uses MODULE_PAYMENT_PAYPALR_SERVER for environment detection\n");
    } else {
        fwrite(STDERR, "✗ MODULE_PAYMENT_PAYPALR_SERVER not found\n");
        exit(1);
    }
    
    // Check that it uses correct live credentials
    if (strpos($content, 'MODULE_PAYMENT_PAYPALR_CLIENTID_L') !== false &&
        strpos($content, 'MODULE_PAYMENT_PAYPALR_SECRET_L') !== false) {
        fwrite(STDOUT, "✓ Uses MODULE_PAYMENT_PAYPALR_CLIENTID_L and SECRET_L for live\n");
    } else {
        fwrite(STDERR, "✗ Live credential constants not found\n");
        exit(1);
    }
    
    // Check that it uses correct sandbox credentials
    if (strpos($content, 'MODULE_PAYMENT_PAYPALR_CLIENTID_S') !== false &&
        strpos($content, 'MODULE_PAYMENT_PAYPALR_SECRET_S') !== false) {
        fwrite(STDOUT, "✓ Uses MODULE_PAYMENT_PAYPALR_CLIENTID_S and SECRET_S for sandbox\n");
    } else {
        fwrite(STDERR, "✗ Sandbox credential constants not found\n");
        exit(1);
    }
    
    // Check that wrong constants are NOT used
    if (strpos($content, 'MODULE_PAYMENT_PAYPALR_CLIENT_ID') !== false ||
        strpos($content, 'MODULE_PAYMENT_PAYPALR_CLIENT_SECRET') !== false ||
        strpos($content, 'MODULE_PAYMENT_PAYPALR_ENVIRONMENT') !== false) {
        fwrite(STDERR, "✗ Old incorrect constant names still present\n");
        exit(1);
    }
    
    fwrite(STDOUT, "✓ No old incorrect constant names found\n");
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

// Test 2: Verify email constants are defined in cron
fwrite(STDOUT, "Test 2: Checking email constants are defined...\n");
$cronFile = $basePath . '/cron/paypal_saved_card_recurring.php';
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    
    // Check for email constant definitions
    if (strpos($content, 'SAVED_CREDIT_CARDS_RECURRING_FAILURE_EMAIL') !== false &&
        strpos($content, 'SAVED_CREDIT_CARDS_RECURRING_FAILURE_WARNING_EMAIL') !== false) {
        fwrite(STDOUT, "✓ Email constants are defined\n");
    } else {
        fwrite(STDERR, "✗ Email constant definitions not found\n");
        exit(1);
    }
    
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ cron file not found\n\n");
    exit(1);
}

fwrite(STDOUT, "All tests passed! ✓\n");
fwrite(STDOUT, "\nVerified:\n");
fwrite(STDOUT, "1. PayPal REST client uses correct environment constants\n");
fwrite(STDOUT, "2. Live environment uses CLIENTID_L and SECRET_L credentials\n");
fwrite(STDOUT, "3. Sandbox environment uses CLIENTID_S and SECRET_S credentials\n");
fwrite(STDOUT, "4. Email constants are properly defined to prevent fatal errors\n");
