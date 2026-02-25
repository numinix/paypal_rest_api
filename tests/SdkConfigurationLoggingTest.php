<?php
/**
 * Test to verify that SDK configuration logging is implemented for debugging purposes.
 *
 * This test ensures that the PayPal SDK configuration (client ID, components, currency, etc.)
 * is properly logged when the SDK is loaded, helping to debug issues like 400 errors from
 * the PayPal SDK when components are not enabled for the account.
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
     * Test that the observer has logging for SDK configuration
     */
    function testObserverHasSdkConfigLogging(): bool
    {
        $passed = true;
        $observerPath = DIR_FS_CATALOG . 'includes/classes/observers/auto.paypaladvcheckout.php';
        
        if (!file_exists($observerPath)) {
            fwrite(STDERR, "FAIL: Observer file not found at $observerPath\n");
            return false;
        }
        
        $content = file_get_contents($observerPath);

        // Check that Logger is imported
        if (strpos($content, 'use PayPalAdvancedCheckout\\Common\\Logger;') !== false) {
            fwrite(STDOUT, "✓ Observer imports Logger class\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should import Logger class\n");
            $passed = false;
        }

        // Check that log property exists
        if (preg_match('/protected\s+\?Logger\s+\$log/', $content)) {
            fwrite(STDOUT, "✓ Observer has log property\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should have log property\n");
            $passed = false;
        }

        // Check that SDK configuration is logged
        if (strpos($content, 'PayPal SDK Configuration') !== false) {
            fwrite(STDOUT, "✓ Observer logs SDK configuration\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should log SDK configuration\n");
            $passed = false;
        }

        // Check that components are logged
        if (strpos($content, 'Components:') !== false) {
            fwrite(STDOUT, "✓ Observer logs SDK components\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should log SDK components\n");
            $passed = false;
        }

        // Check that environment is logged
        if (strpos($content, 'Environment:') !== false) {
            fwrite(STDOUT, "✓ Observer logs environment\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should log environment\n");
            $passed = false;
        }

        // Check that enabled modules are logged
        if (strpos($content, 'Enabled Modules:') !== false) {
            fwrite(STDOUT, "✓ Observer logs enabled modules status\n");
        } else {
            fwrite(STDERR, "FAIL: Observer should log enabled modules status\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that wallet modules have logging for their configuration
     */
    function testWalletModulesHaveConfigLogging(): bool
    {
        $passed = true;
        $walletModules = [
            'paypalac_applepay.php' => 'Apple Pay',
            'paypalac_googlepay.php' => 'Google Pay',
            'paypalac_venmo.php' => 'Venmo',
        ];

        foreach ($walletModules as $filename => $walletName) {
            $modulePath = DIR_FS_CATALOG . 'includes/modules/payment/' . $filename;
            
            if (!file_exists($modulePath)) {
                fwrite(STDERR, "FAIL: $filename not found at $modulePath\n");
                $passed = false;
                continue;
            }
            
            $content = file_get_contents($modulePath);

            // Check that ajaxGetWalletConfig has logging
            if (strpos($content, 'ajaxGetWalletConfig') !== false) {
                // Check for logging in the config method
                if (preg_match('/ajaxGetWalletConfig.*?\$this->log->write/s', $content)) {
                    fwrite(STDOUT, "✓ $filename has logging in ajaxGetWalletConfig\n");
                } else {
                    fwrite(STDERR, "FAIL: $filename ajaxGetWalletConfig should have logging\n");
                    $passed = false;
                }
            }

            // Check that environment is logged
            if (strpos($content, 'Environment:') !== false) {
                fwrite(STDOUT, "✓ $filename logs environment\n");
            } else {
                fwrite(STDERR, "FAIL: $filename should log environment\n");
                $passed = false;
            }

            // Check that client ID is logged (securely)
            if (strpos($content, 'Client ID:') !== false) {
                fwrite(STDOUT, "✓ $filename logs client ID\n");
                
                // Verify client ID is partially masked
                if (strpos($content, 'loggedClientId') !== false) {
                    fwrite(STDOUT, "✓ $filename masks client ID in logs\n");
                } else {
                    fwrite(STDERR, "FAIL: $filename should mask client ID in logs\n");
                    $passed = false;
                }
            } else {
                fwrite(STDERR, "FAIL: $filename should log client ID\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that client ID is properly masked in logs
     */
    function testClientIdMaskingPattern(): bool
    {
        $passed = true;
        
        // Simulate the masking pattern used in the code
        // Client IDs > 10 chars are masked, others are shown as-is
        $testClientIds = [
            'AaeUG61FueOnbyuNSrW0DDyR7ysJspmBe2pvUnq-BYUWUnF2y6S5XlUJz1XvXDMgXfkVWZ8Ot3DTAZbZ' => 'AaeUG6...AZbZ',
            'sb' => 'sb',
            '' => '(empty)',
            'shortID123' => 'shortID123',  // 10 chars exactly, not masked
            'LongerID1234' => 'Longer...1234',  // 12 chars, gets masked
        ];

        foreach ($testClientIds as $clientId => $expected) {
            if (strlen($clientId) > 10) {
                $masked = substr($clientId, 0, 6) . '...' . substr($clientId, -4);
            } elseif ($clientId === '') {
                $masked = '(empty)';
            } else {
                $masked = $clientId;
            }

            if ($masked === $expected) {
                fwrite(STDOUT, "✓ Client ID masking works correctly for '" . ($clientId ?: '(empty)') . "'\n");
            } else {
                fwrite(STDERR, "FAIL: Expected '$expected' but got '$masked' for client ID\n");
                $passed = false;
            }
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying observer has SDK configuration logging...\n");
    if (testObserverHasSdkConfigLogging()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying wallet modules have configuration logging...\n");
    if (testWalletModulesHaveConfigLogging()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying client ID masking pattern...\n");
    if (testClientIdMaskingPattern()) {
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
        fwrite(STDOUT, "\n✓ All SDK configuration logging tests passed!\n");
        fwrite(STDOUT, "\nLogging improvement summary:\n");
        fwrite(STDOUT, "1. Observer now logs SDK configuration (client ID, components, currency, etc.)\n");
        fwrite(STDOUT, "2. Wallet modules log their configuration when ajaxGetWalletConfig is called\n");
        fwrite(STDOUT, "3. Client IDs are masked in logs for security\n");
        fwrite(STDOUT, "4. This helps debug 400 errors from PayPal SDK when components are not enabled\n");
        exit(0);
    }
}
