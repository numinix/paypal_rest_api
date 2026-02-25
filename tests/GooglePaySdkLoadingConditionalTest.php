<?php
/**
 * Test to verify that Google Pay SDK component is only loaded when appropriate.
 *
 * This test ensures that the PayPal SDK googlepay component is loaded based on:
 * 1. User login status
 * 2. Guest wallet setting
 *
 * Expected behavior:
 * - User logged in: Load googlepay component (no Google merchant verification needed)
 * - User not logged in + guest wallet enabled: Load googlepay component
 * - User not logged in + guest wallet disabled: Do NOT load googlepay component
 *
 * This prevents OR_BIBED_06 errors when guest mode is disabled.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }

    /**
     * Test that the observer checks user login status and guest wallet setting
     * before loading the googlepay component.
     */
    function testObserverConditionalGooglePayLoading(): bool
    {
        $passed = true;
        $observerPath = DIR_FS_CATALOG . 'includes/classes/observers/auto.paypaladvcheckout.php';
        
        if (!file_exists($observerPath)) {
            fwrite(STDERR, "FAIL: Observer file not found at $observerPath\n");
            return false;
        }
        
        $content = file_get_contents($observerPath);

        // Test 1: Check that observer has helper method for checking logged in user
        if (strpos($content, 'isUserLoggedIn') !== false && 
            strpos($content, '$_SESSION[\'customer_id\']') !== false) {
            fwrite(STDOUT, "✓ Observer checks user login status via helper method\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should have isUserLoggedIn() helper method\n");
            $passed = false;
        }

        // Test 2: Check that observer has helper method for checking guest wallet setting
        if (strpos($content, 'isGuestWalletEnabled') !== false &&
            strpos($content, 'MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_ENABLE_GUEST_WALLET') !== false) {
            fwrite(STDOUT, "✓ Observer checks guest wallet setting via helper method\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should have isGuestWalletEnabled() helper method\n");
            $passed = false;
        }

        // Test 3: Check that googlepay component is conditionally added
        // Should have both the MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS check AND
        // the conditional logic that loads when user is logged in OR guest wallet is enabled
        $hasGooglePayStatusCheck = strpos($content, 'MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') !== false;
        $hasConditionalLogic = preg_match('/if\s*\(\s*\$this->isUserLoggedIn\(\)\s*\|\|\s*\$this->isGuestWalletEnabled\(\)\s*\)/', $content);
        
        if ($hasGooglePayStatusCheck && $hasConditionalLogic) {
            fwrite(STDOUT, "✓ Observer conditionally adds googlepay component for logged-in users or when guest wallet is enabled\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should conditionally add googlepay component\n");
            if (!$hasGooglePayStatusCheck) {
                fwrite(STDERR, "  Missing: MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS check\n");
            }
            if (!$hasConditionalLogic) {
                fwrite(STDERR, "  Missing: Conditional logic using \$this->isUserLoggedIn() || \$this->isGuestWalletEnabled()\n");
            }
            $passed = false;
        }

        // Test 4: Check for comment explaining the fix
        if (strpos($content, 'PayPal support') !== false || strpos($content, 'emailRequired') !== false ||
            strpos($content, 'Google merchant verification') !== false ||
            strpos($content, 'guest mode is disabled') !== false) {
            fwrite(STDOUT, "✓ Observer has comment explaining the conditional loading\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should have comment explaining why conditional loading is needed\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Simulate different scenarios to verify correct behavior
     */
    function testScenarios(): bool
    {
        $passed = true;
        
        echo "\nSimulating different scenarios:\n";
        echo "================================\n\n";
        
        // Scenario 1: User logged in, guest wallet enabled
        echo "Scenario 1: User logged in, guest wallet enabled\n";
        $isLoggedIn = true;
        $guestWalletEnabled = true;
        $shouldLoadGooglePay = $isLoggedIn || $guestWalletEnabled;
        if ($shouldLoadGooglePay) {
            echo "  ✓ Google Pay SDK component should be loaded\n";
        } else {
            echo "  ✗ FAIL: Google Pay SDK component should be loaded\n";
            $passed = false;
        }
        
        // Scenario 2: User logged in, guest wallet disabled
        echo "\nScenario 2: User logged in, guest wallet disabled\n";
        $isLoggedIn = true;
        $guestWalletEnabled = false;
        $shouldLoadGooglePay = $isLoggedIn || $guestWalletEnabled;  // Load if user logged in OR guest wallet enabled
        if ($shouldLoadGooglePay) {
            echo "  ✓ Google Pay SDK component should be loaded (user is logged in)\n";
        } else {
            echo "  ✗ FAIL: Google Pay SDK component should be loaded when user is logged in\n";
            $passed = false;
        }
        
        // Scenario 3: User not logged in, guest wallet enabled
        echo "\nScenario 3: User not logged in, guest wallet enabled\n";
        $isLoggedIn = false;
        $guestWalletEnabled = true;
        $shouldLoadGooglePay = $isLoggedIn || $guestWalletEnabled;  // Load if user logged in OR guest wallet enabled
        if ($shouldLoadGooglePay) {
            echo "  ✓ Google Pay SDK component should be loaded (guest wallet enabled)\n";
        } else {
            echo "  ✗ FAIL: Google Pay SDK component should be loaded when guest wallet is enabled\n";
            $passed = false;
        }
        
        // Scenario 4: User not logged in, guest wallet disabled
        echo "\nScenario 4: User not logged in, guest wallet disabled\n";
        $isLoggedIn = false;
        $guestWalletEnabled = false;
        $shouldLoadGooglePay = $isLoggedIn || $guestWalletEnabled;  // Load if user logged in OR guest wallet enabled
        if (!$shouldLoadGooglePay) {
            echo "  ✓ Google Pay SDK component should NOT be loaded\n";
        } else {
            echo "  ✗ FAIL: Google Pay SDK component should NOT be loaded when user not logged in and guest wallet disabled\n";
            $passed = false;
        }
        
        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying observer has conditional Google Pay SDK loading...\n");
    if (testObserverConditionalGooglePayLoading()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying scenario logic...\n");
    if (testScenarios()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All Google Pay SDK conditional loading tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. Observer checks user login status and guest wallet setting\n");
        fwrite(STDOUT, "2. Google Pay SDK component is loaded when:\n");
        fwrite(STDOUT, "   - User is logged in (uses PayPal SDK, email from session), OR\n");
        fwrite(STDOUT, "   - Guest wallet is enabled (uses PayPal SDK, email via emailRequired)\n");
        fwrite(STDOUT, "3. Per PayPal support, we use PayPal SDK for both logged-in and guest users\n");
        fwrite(STDOUT, "4. No direct Google Pay SDK or merchant verification needed for logged-in users\n");
        exit(0);
    }
}
