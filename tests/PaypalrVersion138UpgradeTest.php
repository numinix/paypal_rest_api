<?php
declare(strict_types=1);

/**
 * Test to verify v1.3.8 upgrade properly de-registers the paypalrSavedCardRecurring admin page
 *
 * This test ensures that:
 * 1. The version constant is updated to 1.3.8
 * 2. The upgrade case exists in tableCheckup() method
 * 3. The de-registration logic is present
 * 4. The page_key used for deletion matches the registration
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running PayPal v1.3.8 Upgrade Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify version constant is updated to 1.3.8
echo "Test 1: Checking version constant...\n";
$paypalrFile = $basePath . '/includes/modules/payment/paypalr.php';
if (file_exists($paypalrFile)) {
    $content = file_get_contents($paypalrFile);
    if (strpos($content, "protected const CURRENT_VERSION = '1.3.8'") !== false) {
        echo "✓ CURRENT_VERSION constant is set to 1.3.8\n\n";
    } else {
        echo "✗ CURRENT_VERSION constant is not set to 1.3.8\n\n";
        exit(1);
    }
} else {
    echo "✗ paypalr.php file not found\n\n";
    exit(1);
}

// Test 2: Verify version comment in file header is updated
echo "Test 2: Checking file header version comment...\n";
if (strpos($content, "Last updated: v1.3.8") !== false) {
    echo "✓ File header version comment is updated to v1.3.8\n\n";
} else {
    echo "✗ File header version comment is not updated\n\n";
    exit(1);
}

// Test 3: Verify upgrade case exists for v1.3.8
echo "Test 3: Checking for v1.3.8 upgrade case in tableCheckup()...\n";
if (preg_match("/case version_compare\(MODULE_PAYMENT_PAYPALR_VERSION, '1\.3\.8', '<'\)/", $content)) {
    echo "✓ Version 1.3.8 upgrade case exists\n\n";
} else {
    echo "✗ Version 1.3.8 upgrade case not found\n\n";
    exit(1);
}

// Test 4: Verify de-registration logic is present
echo "Test 4: Checking for admin page de-registration logic...\n";
if (strpos($content, "zen_page_key_exists('paypalrSavedCardRecurring')") !== false &&
    strpos($content, "DELETE FROM") !== false &&
    strpos($content, "TABLE_ADMIN_PAGES") !== false) {
    echo "✓ Admin page de-registration logic is present\n\n";
} else {
    echo "✗ Admin page de-registration logic is missing\n\n";
    exit(1);
}

// Test 5: Verify the page_key matches what was registered in v1.3.5
echo "Test 5: Checking that page_key matches registration...\n";
if (preg_match("/WHERE page_key = 'paypalrSavedCardRecurring'/", $content)) {
    echo "✓ page_key matches the registered key from v1.3.5\n\n";
} else {
    echo "✗ page_key does not match\n\n";
    exit(1);
}

// Test 6: Verify comment explains the reason for de-registration
echo "Test 6: Checking for explanatory comment...\n";
if (strpos($content, "saved card subscriptions") !== false &&
    strpos($content, "are now displayed together with REST subscriptions") !== false) {
    echo "✓ Explanatory comment is present\n\n";
} else {
    echo "✗ Explanatory comment is missing\n\n";
    exit(1);
}

// Test 7: Verify Zen Cart version check is included
echo "Test 7: Checking for Zen Cart version check...\n";
if (preg_match("/\\\$zc150.*PROJECT_VERSION_MAJOR.*PROJECT_VERSION_MINOR/s", $content)) {
    echo "✓ Zen Cart version check is present\n\n";
} else {
    echo "✗ Zen Cart version check is missing\n\n";
    exit(1);
}

// Test 8: Verify TABLE_ADMIN_PAGES constant check
echo "Test 8: Checking for TABLE_ADMIN_PAGES constant check...\n";
if (strpos($content, "defined('TABLE_ADMIN_PAGES')") !== false) {
    echo "✓ TABLE_ADMIN_PAGES constant check is present\n\n";
} else {
    echo "✗ TABLE_ADMIN_PAGES constant check is missing\n\n";
    exit(1);
}

echo "========================================\n";
echo "All v1.3.8 upgrade tests passed! ✓\n";
echo "========================================\n\n";

echo "Summary:\n";
echo "- Version constant: 1.3.8 ✓\n";
echo "- File header comment: Updated ✓\n";
echo "- Upgrade case: Present ✓\n";
echo "- De-registration logic: Present ✓\n";
echo "- Page key: Correct ✓\n";
echo "- Explanatory comment: Present ✓\n";
echo "- Zen Cart version check: Present ✓\n";
echo "- TABLE_ADMIN_PAGES check: Present ✓\n";
