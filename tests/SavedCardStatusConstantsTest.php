<?php
/**
 * Test to verify that TEXT_SAVED_CARD_STATUS_* constants are properly defined.
 *
 * This test ensures that the status text constants used by the
 * paypalr_get_vault_status_map() function are properly loaded from the
 * language file without early-return issues.
 *
 * Issue: The backwards compatibility language file had an early return that
 * prevented constants from being defined when NAVBAR_TITLE_1 was already set,
 * causing undefined constant errors.
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

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
}

namespace Tests {

    use PHPUnit\Framework\TestCase;

    final class SavedCardStatusConstantsTest extends TestCase
    {
        public static function setUpBeforeClass(): void
        {
            // Load the language file to ensure constants are defined
            require_once DIR_FS_CATALOG . 'includes/languages/english/account_saved_credit_cards.php';
        }

        public function testLanguageFileDefinesConstants(): void
        {
            // Verify that the language file properly defines all status constants
            // without being blocked by early returns
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_ACTIVE'), 'TEXT_SAVED_CARD_STATUS_ACTIVE should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_INACTIVE'), 'TEXT_SAVED_CARD_STATUS_INACTIVE should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_CANCELED'), 'TEXT_SAVED_CARD_STATUS_CANCELED should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_SUSPENDED'), 'TEXT_SAVED_CARD_STATUS_SUSPENDED should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_PENDING'), 'TEXT_SAVED_CARD_STATUS_PENDING should be defined');
            $this->assertTrue(defined('TEXT_SAVED_CARD_STATUS_EXPIRED'), 'TEXT_SAVED_CARD_STATUS_EXPIRED should be defined');
        }

        public function testLanguageFileNoEarlyReturn(): void
        {
            // Verify that the language file doesn't have an early return
            // that would prevent constants from being defined
            $languageFile = DIR_FS_CATALOG . 'includes/languages/english/account_saved_credit_cards.php';
            $content = file_get_contents($languageFile);
            
            $this->assertStringNotContainsString(
                "if (defined('NAVBAR_TITLE_1')) {\n    return;\n}",
                $content,
                'Language file should not have early return that blocks constant definition'
            );
        }

        public function testGetVaultStatusMapFunction(): void
        {
            // Load just the function definition (not the entire header file which requires DB)
            $headerFile = DIR_FS_CATALOG . 'includes/modules/pages/account_saved_credit_cards/header_php.php';
            $content = file_get_contents($headerFile);
            
            // Extract and evaluate just the function definition
            if (preg_match('/if \(!function_exists\(\'paypalr_get_vault_status_map\'\)\).*?^\}/ms', $content, $matches)) {
                eval('?>' . $matches[0]);
            }

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
