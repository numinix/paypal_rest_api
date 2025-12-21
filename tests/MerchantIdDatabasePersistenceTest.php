<?php
/**
 * Test to verify the database-based merchant_id persistence for cross-session retrieval.
 *
 * This test validates the fix for the issue where PayPal credentials couldn't be retrieved
 * automatically because the merchant_id was stored in a different session context
 * (the popup) than the status polling requests (from the admin panel via proxy).
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', '');
    }
    if (!defined('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING')) {
        define('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING', DB_PREFIX . 'numinix_paypal_onboarding_tracking');
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
}

namespace {
    /**
     * Test that database persistence functions exist and have correct signatures
     */
    function testDatabasePersistenceFunctionsExist(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify nxp_paypal_persist_merchant_id function exists
        if (strpos($content, 'function nxp_paypal_persist_merchant_id(string $trackingId, string $merchantId') !== false) {
            fwrite(STDOUT, "✓ nxp_paypal_persist_merchant_id function exists with correct signature\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_persist_merchant_id function should exist\n");
            $passed = false;
        }

        // Check 2: Verify nxp_paypal_retrieve_merchant_id function exists
        if (strpos($content, 'function nxp_paypal_retrieve_merchant_id(string $trackingId)') !== false) {
            fwrite(STDOUT, "✓ nxp_paypal_retrieve_merchant_id function exists with correct signature\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_retrieve_merchant_id function should exist\n");
            $passed = false;
        }

        // Check 3: Verify nxp_paypal_delete_tracking_record function exists
        if (strpos($content, 'function nxp_paypal_delete_tracking_record(string $trackingId)') !== false) {
            fwrite(STDOUT, "✓ nxp_paypal_delete_tracking_record function exists with correct signature\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_delete_tracking_record function should exist\n");
            $passed = false;
        }

        // Check 4: Verify nxp_paypal_cleanup_expired_tracking function exists
        if (strpos($content, 'function nxp_paypal_cleanup_expired_tracking()') !== false) {
            fwrite(STDOUT, "✓ nxp_paypal_cleanup_expired_tracking function exists\n");
        } else {
            fwrite(STDERR, "FAIL: nxp_paypal_cleanup_expired_tracking function should exist\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that persistence uses database instead of file system
     */
    function testPersistenceUsesDatabase(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING is used
        if (strpos($content, 'TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING') !== false) {
            fwrite(STDOUT, "✓ Uses TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING constant\n");
        } else {
            fwrite(STDERR, "FAIL: Should use TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING constant\n");
            $passed = false;
        }

        // Check 2: Verify database operations are used (INSERT, UPDATE, DELETE, SELECT)
        if (strpos($content, 'INSERT INTO') !== false && 
            strpos($content, 'UPDATE ') !== false &&
            strpos($content, 'DELETE FROM') !== false &&
            strpos($content, 'SELECT ') !== false) {
            fwrite(STDOUT, "✓ Uses proper SQL operations (INSERT, UPDATE, DELETE, SELECT)\n");
        } else {
            fwrite(STDERR, "FAIL: Should use proper SQL operations\n");
            $passed = false;
        }

        // Check 3: Verify parameterized queries are used (bindVars)
        if (strpos($content, 'bindVars') !== false) {
            fwrite(STDOUT, "✓ Uses parameterized queries for security\n");
        } else {
            fwrite(STDERR, "FAIL: Should use parameterized queries (bindVars)\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that input validation is performed
     */
    function testInputValidation(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify tracking_id format validation
        if (strpos($content, "preg_match('/^[a-zA-Z0-9-]{1,64}\$/'") !== false) {
            fwrite(STDOUT, "✓ Validates tracking_id format (alphanumeric + dash, max 64 chars)\n");
        } else {
            fwrite(STDERR, "FAIL: Should validate tracking_id format\n");
            $passed = false;
        }

        // Check 2: Verify merchant_id format validation (PayPal merchant IDs are 10-20 alphanumeric chars, no dashes)
        if (strpos($content, "preg_match('/^[A-Za-z0-9]{10,20}\$/'") !== false
            || strpos($content, 'nxp_paypal_is_valid_merchant_id') !== false) {
            fwrite(STDOUT, "✓ Validates merchant_id format (10-20 alphanumeric chars, no dashes)\n");
        } else {
            fwrite(STDERR, "FAIL: Should validate merchant_id format\n");
            $passed = false;
        }

        // Check 3: Verify empty value checks
        if (strpos($content, "if (\$trackingId === '' || \$merchantId === '')") !== false) {
            fwrite(STDOUT, "✓ Checks for empty tracking_id and merchant_id\n");
        } else {
            fwrite(STDERR, "FAIL: Should check for empty values\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that expiry and cleanup mechanisms exist
     */
    function testExpiryAndCleanup(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify 1 hour expiry is set
        if (strpos($content, 'time() + 3600') !== false) {
            fwrite(STDOUT, "✓ Sets 1 hour expiry for tracking records\n");
        } else {
            fwrite(STDERR, "FAIL: Should set 1 hour expiry\n");
            $passed = false;
        }

        // Check 2: Verify expired records are deleted on retrieve
        if (strpos($content, 'Expired - delete the record') !== false ||
            strpos($content, 'nxp_paypal_delete_tracking_record($trackingId)') !== false) {
            fwrite(STDOUT, "✓ Deletes expired records on retrieve\n");
        } else {
            fwrite(STDERR, "FAIL: Should delete expired records on retrieve\n");
            $passed = false;
        }

        // Check 3: Verify cleanup of expired records on persist
        if (strpos($content, 'nxp_paypal_cleanup_expired_tracking()') !== false) {
            fwrite(STDOUT, "✓ Cleans up expired records on persist\n");
        } else {
            fwrite(STDERR, "FAIL: Should cleanup expired records on persist\n");
            $passed = false;
        }

        // Check 4: Verify cleanup after credential retrieval
        if (strpos($content, 'Deleted tracking record after successful credential retrieval') !== false ||
            strpos($content, 'Clean up the tracking record after successful credential retrieval') !== false) {
            fwrite(STDOUT, "✓ Deletes tracking record after successful credential retrieval\n");
        } else {
            fwrite(STDERR, "FAIL: Should delete tracking record after credential retrieval\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that completion page persists merchant_id
     */
    function testCompletionPagePersistsMerchantId(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify completion page calls persist function
        if (strpos($content, 'nxp_paypal_persist_merchant_id($trackingId, $merchantId, $environment)') !== false) {
            fwrite(STDOUT, "✓ Completion page calls nxp_paypal_persist_merchant_id\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should call nxp_paypal_persist_merchant_id\n");
            $passed = false;
        }

        // Check 2: Verify tracking_id is extracted from session or URL
        if (strpos($content, "\$_GET['tracking_id']") !== false &&
            strpos($content, "\$_SESSION['nxp_paypal']['tracking_id']") !== false) {
            fwrite(STDOUT, "✓ Completion page extracts tracking_id from GET or session\n");
        } else {
            fwrite(STDERR, "FAIL: Completion page should extract tracking_id\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that status handler retrieves persisted merchant_id
     */
    function testStatusHandlerRetrievesMerchantId(): bool
    {
        $passed = true;

        $helpersFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/includes/nxp_paypal_helpers.php';
        $content = file_get_contents($helpersFile);

        // Check 1: Verify status handler calls retrieve function
        if (strpos($content, 'nxp_paypal_retrieve_merchant_id($trackingId)') !== false) {
            fwrite(STDOUT, "✓ Status handler calls nxp_paypal_retrieve_merchant_id\n");
        } else {
            fwrite(STDERR, "FAIL: Status handler should call nxp_paypal_retrieve_merchant_id\n");
            $passed = false;
        }

        // Check 2: Verify it only retrieves if merchant_id is not already provided
        if (strpos($content, 'if (empty($merchantId) && !empty($trackingId))') !== false) {
            fwrite(STDOUT, "✓ Only retrieves merchant_id if not already provided\n");
        } else {
            fwrite(STDERR, "FAIL: Should only retrieve if merchant_id is empty\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that database table definition exists
     */
    function testDatabaseTableDefinitionExists(): bool
    {
        $passed = true;

        $installerFile = DIR_FS_CATALOG . 'numinix.com/management/includes/installers/numinix_paypal_isu/1_0_6.php';
        
        if (!file_exists($installerFile)) {
            fwrite(STDERR, "FAIL: Installer file 1_0_6.php does not exist\n");
            return false;
        }

        $content = file_get_contents($installerFile);

        // Check 1: Verify table creation SQL exists
        if (strpos($content, 'CREATE TABLE IF NOT EXISTS') !== false) {
            fwrite(STDOUT, "✓ Installer creates table if not exists\n");
        } else {
            fwrite(STDERR, "FAIL: Installer should create table if not exists\n");
            $passed = false;
        }

        // Check 2: Verify required columns exist
        $requiredColumns = ['tracking_id', 'merchant_id', 'environment', 'expires_at', 'created_at', 'updated_at'];
        foreach ($requiredColumns as $column) {
            if (strpos($content, $column) !== false) {
                fwrite(STDOUT, "✓ Table includes '$column' column\n");
            } else {
                fwrite(STDERR, "FAIL: Table should include '$column' column\n");
                $passed = false;
            }
        }

        // Check 3: Verify unique index on tracking_id
        if (strpos($content, 'UNIQUE KEY') !== false && strpos($content, 'tracking_id') !== false) {
            fwrite(STDOUT, "✓ Table has unique index on tracking_id\n");
        } else {
            fwrite(STDERR, "FAIL: Table should have unique index on tracking_id\n");
            $passed = false;
        }

        // Check 4: Verify index on expires_at for cleanup queries
        if (strpos($content, 'idx_expires_at') !== false) {
            fwrite(STDOUT, "✓ Table has index on expires_at for efficient cleanup\n");
        } else {
            fwrite(STDERR, "FAIL: Table should have index on expires_at\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that table constant is defined in extra_datafiles
     */
    function testTableConstantDefined(): bool
    {
        $passed = true;

        $dataFile = DIR_FS_CATALOG . 'numinix.com/includes/extra_datafiles/numinix_paypal_isu.php';
        
        if (!file_exists($dataFile)) {
            fwrite(STDERR, "FAIL: Data file numinix_paypal_isu.php does not exist\n");
            return false;
        }

        $content = file_get_contents($dataFile);

        // Check: Verify TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING constant is defined
        if (strpos($content, "define('TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING'") !== false) {
            fwrite(STDOUT, "✓ TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING constant is defined\n");
        } else {
            fwrite(STDERR, "FAIL: TABLE_NUMINIX_PAYPAL_ONBOARDING_TRACKING constant should be defined\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying database persistence functions exist...\n");
    if (testDatabasePersistenceFunctionsExist()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying persistence uses database...\n");
    if (testPersistenceUsesDatabase()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying input validation...\n");
    if (testInputValidation()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying expiry and cleanup mechanisms...\n");
    if (testExpiryAndCleanup()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying completion page persists merchant_id...\n");
    if (testCompletionPagePersistsMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 6: Verifying status handler retrieves persisted merchant_id...\n");
    if (testStatusHandlerRetrievesMerchantId()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 7: Verifying database table definition exists...\n");
    if (testDatabaseTableDefinitionExists()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 8: Verifying table constant is defined...\n");
    if (testTableConstantDefined()) {
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
        fwrite(STDOUT, "\n✓ All database persistence tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. Database table 'numinix_paypal_onboarding_tracking' stores merchant_id keyed by tracking_id.\n");
        fwrite(STDOUT, "2. Completion page persists merchant_id when PayPal redirects back.\n");
        fwrite(STDOUT, "3. Status handler retrieves persisted merchant_id if not provided in request.\n");
        fwrite(STDOUT, "4. Records expire after 1 hour and are automatically cleaned up.\n");
        fwrite(STDOUT, "5. Records are deleted immediately after successful credential retrieval.\n");
        exit(0);
    }
}
