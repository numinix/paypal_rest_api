<?php
/**
 * Test to verify that the observer initializes when any PayPal payment module is enabled,
 * not just the base paypalac module. This ensures wallet modules can function independently.
 *
 * Issue: If paypalac.php is disabled (but still installed), the wallet modules should still
 * be able to process payments on their own using the shared credentials.
 */
declare(strict_types=1);

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}

/**
 * Test that observer checks if any PayPal module is enabled
 */
function testObserverChecksAnyModuleEnabled(): bool
{
    $observers = [
        'includes/classes/observers/auto.paypalrestful.php',
        'includes/classes/observers/auto.paypalrestful_recurring.php',
        'includes/classes/observers/auto.paypalrestful_vault.php',
    ];
    
    foreach ($observers as $observerPath) {
        $observerFile = DIR_FS_CATALOG . $observerPath;
        $content = file_get_contents($observerFile);
        
        // Check that the code first checks for MODULE_PAYMENT_PAYPALAC_VERSION (base module installed)
        if (strpos($content, 'MODULE_PAYMENT_PAYPALAC_VERSION') === false) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check if base module is installed\n");
            return false;
        }
        
        // Check that the code checks for each module's status being 'True' with OR logic
        // This ensures any enabled module will allow the observer to initialize
        $hasBaseCheck = strpos($content, "MODULE_PAYMENT_PAYPALAC_STATUS === 'True'") !== false;
        $hasCreditCardCheck = strpos($content, "MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS === 'True'") !== false;
        $hasApplePayCheck = strpos($content, "MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS === 'True'") !== false;
        $hasGooglePayCheck = strpos($content, "MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS === 'True'") !== false;
        $hasVenmoCheck = strpos($content, "MODULE_PAYMENT_PAYPALAC_VENMO_STATUS === 'True'") !== false;
        
        if (!$hasBaseCheck) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check base module status\n");
            return false;
        }
        if (!$hasCreditCardCheck) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check credit card module status\n");
            return false;
        }
        if (!$hasApplePayCheck) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check Apple Pay module status\n");
            return false;
        }
        if (!$hasGooglePayCheck) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check Google Pay module status\n");
            return false;
        }
        if (!$hasVenmoCheck) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check Venmo module status\n");
            return false;
        }
        
        // Check that there's a variable storing the result (anyModuleEnabled or similar)
        if (strpos($content, '$anyModuleEnabled') === false) {
            fwrite(STDERR, "FAIL: $observerPath doesn't use anyModuleEnabled variable pattern\n");
            return false;
        }
        
        // Check for OR operator between status checks
        if (strpos($content, '||') === false) {
            fwrite(STDERR, "FAIL: $observerPath doesn't use OR logic between module status checks\n");
            return false;
        }
    }
    
    fwrite(STDOUT, "  ✓ All observers properly check if any PayPal module is enabled\n");
    return true;
}

/**
 * Test that observer still requires base module to be installed
 */
function testObserverRequiresBaseModuleInstalled(): bool
{
    $observers = [
        'includes/classes/observers/auto.paypalrestful.php',
        'includes/classes/observers/auto.paypalrestful_recurring.php',
        'includes/classes/observers/auto.paypalrestful_vault.php',
    ];
    
    foreach ($observers as $observerPath) {
        $observerFile = DIR_FS_CATALOG . $observerPath;
        $content = file_get_contents($observerFile);
        
        // The observer should check MODULE_PAYMENT_PAYPALAC_VERSION first
        // and return early if not defined
        $pattern = '/if\s*\(\s*!defined\s*\(\s*[\'"]MODULE_PAYMENT_PAYPALAC_VERSION[\'"]\s*\)\s*\)\s*\{/';
        if (!preg_match($pattern, $content)) {
            fwrite(STDERR, "FAIL: $observerPath doesn't check if base module is installed before checking statuses\n");
            return false;
        }
    }
    
    fwrite(STDOUT, "  ✓ All observers require base module to be installed\n");
    return true;
}

/**
 * Test that the old problematic pattern is removed
 */
function testOldPatternRemoved(): bool
{
    $observers = [
        'includes/classes/observers/auto.paypalrestful.php',
        'includes/classes/observers/auto.paypalrestful_recurring.php',
        'includes/classes/observers/auto.paypalrestful_vault.php',
    ];
    
    foreach ($observers as $observerPath) {
        $observerFile = DIR_FS_CATALOG . $observerPath;
        $content = file_get_contents($observerFile);
        
        // The old pattern that caused the bug was:
        // if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS') || MODULE_PAYMENT_PAYPALAC_STATUS !== 'True')
        // This should NOT exist anymore - observers should check VERSION first, then check
        // if ANY module is enabled (not just STATUS alone with OR for undefined check)
        
        // Build regex pattern to detect the problematic pattern
        $definedCheck = '!defined\s*\(\s*[\'"]MODULE_PAYMENT_PAYPALAC_STATUS[\'"]\s*\)';
        $statusCheck = 'MODULE_PAYMENT_PAYPALAC_STATUS\s*!==\s*[\'"]True[\'"]';
        $oldPattern = '/if\s*\(\s*' . $definedCheck . '\s*\|\|\s*' . $statusCheck . '\s*\)/';
        
        if (preg_match($oldPattern, $content)) {
            fwrite(STDERR, "FAIL: $observerPath still has old pattern that checks only base module status\n");
            return false;
        }
    }
    
    fwrite(STDOUT, "  ✓ Old problematic pattern has been removed from all observers\n");
    return true;
}

// Run the tests
$failures = 0;

fwrite(STDOUT, "Test 1: Verifying observer checks if any PayPal module is enabled...\n");
if (!testObserverChecksAnyModuleEnabled()) {
    $failures++;
}

fwrite(STDOUT, "\nTest 2: Verifying observer requires base module to be installed...\n");
if (!testObserverRequiresBaseModuleInstalled()) {
    $failures++;
}

fwrite(STDOUT, "\nTest 3: Verifying old problematic pattern is removed...\n");
if (!testOldPatternRemoved()) {
    $failures++;
}

// Summary
if ($failures > 0) {
    fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
    exit(1);
} else {
    fwrite(STDOUT, "\n✓ All observer independent module tests passed!\n");
    exit(0);
}
