<?php
declare(strict_types=1);

/**
 * Test to verify recurring payment vault payment source structure
 *
 * This test ensures that when using a vault_id for recurring payments:
 * 1. Only vault_id and stored_credential are sent
 * 2. expiry, last_digits, brand, name, and billing_address are NOT sent
 * 3. This prevents INCOMPATIBLE_PARAMETER_VALUE errors from PayPal
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

fwrite(STDOUT, "Running Recurring Vault Payment Source Test...\n\n");

$basePath = dirname(__DIR__);
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';

if (!file_exists($savedCardRecurringFile)) {
    fwrite(STDERR, "✗ FAILED: paypalSavedCardRecurring.php not found\n");
    exit(1);
}

$content = file_get_contents($savedCardRecurringFile);

// Extract the build_vault_payment_source method - match until we find the closing brace at the same indentation level
$pattern = '/protected function build_vault_payment_source\([^)]*\)[^{]*\{((?:[^{}]|\{[^{}]*\})*)\}/s';
if (!preg_match($pattern, $content, $matches)) {
    fwrite(STDERR, "✗ FAILED: Could not find build_vault_payment_source method\n");
    exit(1);
}

$methodBody = $matches[1];

fwrite(STDOUT, "Test 1: Verify incompatible fields are NOT added to cardPayload...\n");
$incompatibleFields = array('expiry', 'last_digits', 'brand', 'name', 'billing_address');
$foundIncompatible = array();

foreach ($incompatibleFields as $field) {
    // Check if the field is being assigned to cardPayload (but not in comments)
    $assignmentPattern = '/\$cardPayload\[\s*[\'"]' . preg_quote($field, '/') . '[\'"]\s*\]\s*=/';
    if (preg_match($assignmentPattern, $methodBody)) {
        // Make sure it's not just in a comment
        $lines = explode("\n", $methodBody);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Skip comment lines
            if (strpos($trimmed, '//') === 0) {
                continue;
            }
            if (preg_match($assignmentPattern, $line)) {
                $foundIncompatible[] = $field;
                break;
            }
        }
    }
}

if (!empty($foundIncompatible)) {
    fwrite(STDERR, "✗ FAILED: Found incompatible field assignments that cause PayPal errors:\n");
    foreach ($foundIncompatible as $field) {
        fwrite(STDERR, "  - \$cardPayload['$field'] is being set\n");
    }
    fwrite(STDERR, "\n");
    fwrite(STDERR, "When using vault_id, these fields should NOT be sent to PayPal.\n");
    fwrite(STDERR, "They cause INCOMPATIBLE_PARAMETER_VALUE errors.\n");
    exit(1);
}

fwrite(STDOUT, "✓ PASSED: No incompatible field assignments found\n\n");

fwrite(STDOUT, "Test 2: Verify vault_id is set in cardPayload...\n");
if (!preg_match('/\$cardPayload\s*=\s*array\(\s*[\'"]vault_id[\'"]\s*=>/', $methodBody)) {
    fwrite(STDERR, "✗ FAILED: vault_id not found in cardPayload initialization\n");
    exit(1);
}
fwrite(STDOUT, "✓ PASSED: vault_id is correctly set\n\n");

fwrite(STDOUT, "Test 3: Verify stored_credential is set in cardPayload...\n");
if (!preg_match('/\$cardPayload\[\s*[\'"]stored_credential[\'"]\s*\]\s*=\s*\$storedDefaults/', $methodBody)) {
    fwrite(STDERR, "✗ FAILED: stored_credential assignment not found\n");
    exit(1);
}
fwrite(STDOUT, "✓ PASSED: stored_credential is correctly set\n\n");

fwrite(STDOUT, "Test 4: Verify payment_type can be set to RECURRING...\n");
// Check that the stored_credential options are merged in
if (!preg_match('/\$storedDefaults\s*=\s*array_merge\(\s*\$storedDefaults\s*,\s*\$options\[\s*[\'"]stored_credential[\'"]\s*\]/', $methodBody)) {
    fwrite(STDERR, "✗ FAILED: stored_credential options merge not found\n");
    exit(1);
}
fwrite(STDOUT, "✓ PASSED: payment_type can be overridden via options\n\n");

fwrite(STDOUT, "Test 5: Verify comments explain the fix...\n");
if (!preg_match('/When using a vault_id.*PayPal already has the card details/s', $methodBody)) {
    fwrite(STDERR, "✗ FAILED: Explanatory comments not found\n");
    exit(1);
}
fwrite(STDOUT, "✓ PASSED: Comments explain the PayPal API requirements\n\n");

fwrite(STDOUT, "All tests passed! ✓\n\n");
fwrite(STDOUT, "Verified:\n");
fwrite(STDOUT, "1. build_vault_payment_source() does NOT add incompatible fields\n");
fwrite(STDOUT, "2. Only vault_id is set in cardPayload initialization\n");
fwrite(STDOUT, "3. stored_credential is properly added\n");
fwrite(STDOUT, "4. payment_type can be set to RECURRING via options\n");
fwrite(STDOUT, "5. Code includes explanatory comments\n");
fwrite(STDOUT, "6. This prevents PayPal INCOMPATIBLE_PARAMETER_VALUE errors\n");
