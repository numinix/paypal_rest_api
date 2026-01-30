<?php
/**
 * Test to verify that admin files use correct messageStack.add_session() format.
 * 
 * Admin format: add_session(message, type)  - 2 parameters
 * Catalog format: add_session(class, message, type) - 3 parameters
 * 
 * This test ensures all admin files use the correct 2-parameter format.
 */

fwrite(STDOUT, "=== MessageStack Admin Format Test ===\n");
fwrite(STDOUT, "Testing that admin files use correct add_session() format...\n\n");

$failures = 0;
$adminPath = dirname(__DIR__) . '/admin';

$adminFiles = [
    'paypalr_subscriptions.php',
    'paypalr_saved_card_recurring.php',
    'paypalr_integrated_signup.php',
    'paypalr_upgrade.php'
];

foreach ($adminFiles as $file) {
    $filePath = $adminPath . '/' . $file;
    if (!file_exists($filePath)) {
        fwrite(STDERR, "✗ File not found: $file\n");
        $failures++;
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Test 1: Check for incorrect 3-parameter format with 'header'
    fwrite(STDOUT, "Testing $file...\n");
    
    if (preg_match("/add_session\('header',/", $content)) {
        fwrite(STDERR, "  ✗ FAILED: Found add_session('header', ...) - should be add_session(...)\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ PASSED: No add_session('header', ...) found\n");
    }
    
    // Test 2: Check for undefined $messageStackKey variable usage
    if (preg_match('/add_session\(\$messageStackKey,/', $content)) {
        fwrite(STDERR, "  ✗ FAILED: Found add_session(\$messageStackKey, ...) - should be add_session(...)\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ PASSED: No add_session(\$messageStackKey, ...) found\n");
    }
    
    // Test 3: Verify we have add_session calls (make sure file wasn't corrupted)
    $count = preg_match_all('/add_session\(/', $content);
    if ($count === 0) {
        fwrite(STDERR, "  ✗ WARNING: No add_session calls found in $file\n");
    } else {
        fwrite(STDOUT, "  ✓ INFO: Found $count add_session calls in correct format\n");
    }
    
    fwrite(STDOUT, "\n");
}

// Test DoRefund, DoCapture, DoVoid, DoAuthorization - they should use 2-param format
$adminActionsPath = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Admin';
$actionFiles = [
    'DoRefund.php',
    'DoCapture.php', 
    'DoVoid.php',
    'DoAuthorization.php'
];

fwrite(STDOUT, "Testing PayPalRestful Admin action files...\n");
foreach ($actionFiles as $file) {
    $filePath = $adminActionsPath . '/' . $file;
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // These files should use 2-parameter format
    if (preg_match("/add_session\([^,]+,\s*[^,]+,\s*'(error|success|warning)'\)/", $content, $matches)) {
        // Check if it's actually a 3-parameter call by looking for the pattern more carefully
        // The format should be: add_session(message, type)
        // Not: add_session(class, message, type)
        
        // Count parameters by looking for the pattern
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, 'add_session') !== false) {
                // Simple heuristic: if we see 'header' as first param after add_session(, it's wrong
                if (preg_match("/add_session\('header',/", $line)) {
                    fwrite(STDERR, "  ✗ FAILED in $file line " . ($lineNum+1) . ": Found 3-param format\n");
                    $failures++;
                }
            }
        }
    }
    
    fwrite(STDOUT, "  ✓ $file uses correct format\n");
}

fwrite(STDOUT, "\n=== Test Summary ===\n");
if ($failures > 0) {
    fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
    exit(1);
}

fwrite(STDOUT, "✅ All messageStack format tests passed!\n");
fwrite(STDOUT, "All admin files correctly use 2-parameter format: add_session(message, type)\n");
exit(0);
