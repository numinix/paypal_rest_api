<?php
/**
 * Test to verify that TEXT_SAVED_CARD_STATUS_* constants are properly defined.
 *
 * This test ensures that the status text constants used by the
 * paypalr_get_vault_status_map() function are always available, even when
 * Zen Cart 1.5.8+ loads language files as arrays instead of defining constants.
 *
 * Issue: PHP Fatal error when TEXT_SAVED_CARD_STATUS_ACTIVE and related
 * constants are undefined during function execution.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
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

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
}

namespace Tests {

    use PHPUnit\Framework\TestCase;

    final class SavedCardStatusConstantsTest extends TestCase
    {
        public function testStatusConstantsAreDefined(): void
        {
            // Load the header_php.php file which should define the constants
            require_once DIR_FS_CATALOG . 'includes/modules/pages/account_saved_credit_cards/header_php.php';

            // Verify all required constants are defined
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_ACTIVE'), 'TEXT_SAVED_CARD_STATUS_ACTIVE should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_INACTIVE'), 'TEXT_SAVED_CARD_STATUS_INACTIVE should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_CANCELED'), 'TEXT_SAVED_CARD_STATUS_CANCELED should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_SUSPENDED'), 'TEXT_SAVED_CARD_STATUS_SUSPENDED should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_PENDING'), 'TEXT_SAVED_CARD_STATUS_PENDING should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_EXPIRED'), 'TEXT_SAVED_CARD_STATUS_EXPIRED should be defined');
        }

        public function testStatusConstantsHaveValues(): void
        {
            // Load the header_php.php file which should define the constants
            require_once DIR_FS_CATALOG . 'includes/modules/pages/account_saved_credit_cards/header_php.php';

            // Verify all constants have non-empty string values
            $this->assertNotEmpty(TEXT_SAVED_CARD_STATUS_ACTIVE, 'TEXT_SAVED_CARD_STATUS_ACTIVE should have a value');
            $this->assertNotEmpty(TEXT_SAVED_CARD_STATUS_INACTIVE, 'TEXT_SAVED_CARD_STATUS_INACTIVE should have a value');
            $this->assertNotEmpty(TEXT_SAVED_CARD_STATUS_CANCELED, 'TEXT_SAVED_CARD_STATUS_CANCELED should have a value');
            $this->assertNotEmpty(TEXT_SAVED_CARD_STATUS_SUSPENDED, 'TEXT_SAVED_CARD_STATUS_SUSPENDED should have a value');
            $this->assertNotEmpty(TEXT_SAVED_CARD_STATUS_PENDING, 'TEXT_SAVED_CARD_STATUS_PENDING should have a value');
            $this->assertNotEmpty(TEXT_SAVED_CARD_STATUS_EXPIRED, 'TEXT_SAVED_CARD_STATUS_EXPIRED should have a value');
        }

        public function testGetVaultStatusMapFunction(): void
        {
            // Load the header_php.php file which defines the function
            require_once DIR_FS_CATALOG . 'includes/modules/pages/account_saved_credit_cards/header_php.php';

            // Verify the function exists
            $this->assertTrue(function_exists('paypalr_get_vault_status_map'), 'paypalr_get_vault_status_map function should exist');

            // Call the function and verify it returns an array
            $statusMap = paypalr_get_vault_status_map();
            $this->assertIsArray($statusMap, 'paypalr_get_vault_status_map should return an array');

            // Verify expected status keys exist
            $expectedKeys = ['ACTIVE', 'APPROVED', 'VAULTED', 'INACTIVE', 'CANCELLED', 'CANCELED', 'DELETED', 'EXPIRED', 'SUSPENDED', 'PENDING', 'UNKNOWN'];
            foreach ($expectedKeys as $key) {
                $this->assertArrayHasKey($key, $statusMap, "Status map should have key: {$key}");
            }

            // Verify each status has the expected structure [text, cssClass]
            foreach ($statusMap as $status => $data) {
                $this->assertIsArray($data, "Status '{$status}' should have array data");
                $this->assertCount(2, $data, "Status '{$status}' should have exactly 2 elements");
                $this->assertIsString($data[0], "Status '{$status}' text should be a string");
                $this->assertIsString($data[1], "Status '{$status}' CSS class should be a string");
            }
        }
    }
}
