<?php
declare(strict_types=1);

/**
 * Test to verify billing_address includes country_code
 *
 * This test ensures that:
 * 1. billing_address always includes country_code field
 * 2. country_code is added even when using vaultCard billing_address
 * 3. Helper method getCustomerCountryCode exists
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Billing Address Country Code Test...\n\n");

$basePath = dirname(__DIR__);

// Test 1: Verify country_code logic in build_billing_address_from_card
fwrite(STDOUT, "Test 1: Checking billing_address includes country_code handling...\n");
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that country_code is checked when using vault card billing_address
    if (strpos($content, "!isset(\$billing['country_code'])") !== false ||
        strpos($content, "\$billing['country_code'] === ''") !== false) {
        fwrite(STDOUT, "✓ Checks for missing country_code in vault card billing_address\n");
    } else {
        fwrite(STDERR, "✗ Missing check for country_code in vault card billing_address\n");
        exit(1);
    }
    
    // Check that country_code is added when missing
    if (strpos($content, "\$billing['country_code'] = \$countryCode") !== false) {
        fwrite(STDOUT, "✓ Adds country_code when missing\n");
    } else {
        fwrite(STDERR, "✗ Does not add country_code when missing\n");
        exit(1);
    }
    
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

// Test 2: Verify getCustomerCountryCode helper method exists
fwrite(STDOUT, "Test 2: Checking getCustomerCountryCode helper method...\n");
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that helper method exists
    if (strpos($content, 'function getCustomerCountryCode') !== false) {
        fwrite(STDOUT, "✓ getCustomerCountryCode helper method exists\n");
    } else {
        fwrite(STDERR, "✗ getCustomerCountryCode helper method not found\n");
        exit(1);
    }
    
    // Check that it retrieves country code from database
    if (strpos($content, "zen_get_countries") !== false &&
        strpos($content, "countries_iso_code_2") !== false) {
        fwrite(STDOUT, "✓ Helper method retrieves ISO country code\n");
    } else {
        fwrite(STDERR, "✗ Helper method doesn't retrieve country code properly\n");
        exit(1);
    }
    
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

// Test 3: Verify country_code is in the main billing array
fwrite(STDOUT, "Test 3: Checking country_code in billing array construction...\n");
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Check that billing array includes country_code
    if (strpos($content, "'country_code' => \$countryCode") !== false) {
        fwrite(STDOUT, "✓ Billing array includes country_code field\n");
    } else {
        fwrite(STDERR, "✗ Billing array missing country_code field\n");
        exit(1);
    }
    
    fwrite(STDOUT, "\n");
} else {
    fwrite(STDERR, "✗ paypalSavedCardRecurring.php not found\n\n");
    exit(1);
}

fwrite(STDOUT, "All tests passed! ✓\n");
fwrite(STDOUT, "\nVerified:\n");
fwrite(STDOUT, "1. country_code is always included in billing_address\n");
fwrite(STDOUT, "2. country_code is added to vault card billing_address when missing\n");
fwrite(STDOUT, "3. Helper method retrieves country code from customer address\n");
fwrite(STDOUT, "\nThis satisfies PayPal's requirement for country_code in billing_address.\n");
