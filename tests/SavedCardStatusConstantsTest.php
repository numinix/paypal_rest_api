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

        public function testConstantsCanBeUsedInFunctionReturn(): void
        {
            // Verify that the constants can be used in a function that returns an array
            // This simulates what paypalr_get_vault_status_map does
            $statusMap = [
                'ACTIVE' => [TEXT_SAVED_CARD_STATUS_ACTIVE, 'is-active'],
                'INACTIVE' => [TEXT_SAVED_CARD_STATUS_INACTIVE, 'is-inactive'],
            ];

            $this->assertIsArray($statusMap);
            $this->assertArrayHasKey('ACTIVE', $statusMap);
            $this->assertArrayHasKey('INACTIVE', $statusMap);
            $this->assertEquals('Active', $statusMap['ACTIVE'][0]);
            $this->assertEquals('Inactive', $statusMap['INACTIVE'][0]);
        }
    }
}
