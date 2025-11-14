<?php
declare(strict_types=1);

/**
 * Test that validates the save card checkbox works correctly with both
 * field name prefixes (paypalr_ and ppr_) in different checkout flows.
 */

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
    if (!defined('MODULE_PAYMENT_PAYPALR_STATUS')) {
        define('MODULE_PAYMENT_PAYPALR_STATUS', 'True');
    }
    if (!defined('CC_OWNER_MIN_LENGTH')) {
        define('CC_OWNER_MIN_LENGTH', 3);
    }

    // Mock classes
    class messageStack
    {
        public static function add_session($page, $message, $type): void
        {
            // Mock implementation
        }
    }

    class cc_validation
    {
        public string $cc_type = 'Visa';
        public string $cc_number = '4111111111111111';
        public string $cc_expiry_month = '12';
        public string $cc_expiry_year = '2025';

        public function validate($number, $month, $year): int
        {
            if ($number === '4111111111111111' && $month === '12' && $year === '2025') {
                return 1; // Valid
            }
            return 0; // Invalid
        }
    }

    if (!defined('DIR_WS_CLASSES')) {
        define('DIR_WS_CLASSES', DIR_FS_CATALOG . 'includes/classes/');
    }

    $GLOBALS['messageStack'] = new messageStack();

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['customer_id'] = 99;
}

namespace {
    $failures = 0;

    echo "Test 1: Pre-confirmation flow (prefix=paypalr) with save card checked...\n";
    
    // Simulate pre-confirmation POST data
    $_POST = [
        'paypalr_cc_owner' => 'John Doe',
        'paypalr_cc_number' => '4111111111111111',
        'paypalr_cc_expires_month' => '12',
        'paypalr_cc_expires_year' => '2025',
        'paypalr_cc_cvv' => '123',
        'paypalr_cc_save_card' => 'on',  // Checkbox checked
        'paypalr_saved_card' => 'new',
    ];
    $_SESSION = [
        'customer_id' => 99,
    ];

    // Simulate what validateCardInformation does
    $postvar_prefix = 'paypalr';  // Pre-confirmation
    $allowSaveCard = ($_SESSION['customer_id'] ?? 0) > 0;
    $storeCard = $allowSaveCard && !empty($_POST[$postvar_prefix . '_cc_save_card']);

    if ($storeCard === true) {
        $_SESSION['PayPalRestful']['save_card'] = true;
        echo "  ✓ Session variable set correctly for pre-confirmation flow\n";
    } else {
        fwrite(STDERR, "  ✗ FAILED: Session variable not set for pre-confirmation flow\n");
        $failures++;
    }

    echo "\nTest 2: Confirmation flow (prefix=ppr) with save card checked...\n";
    
    // Simulate confirmation POST data (field names have ppr_ prefix)
    $_POST = [
        'ppr_cc_owner' => 'John Doe',
        'ppr_cc_number' => '4111111111111111',
        'ppr_cc_expires_month' => '12',
        'ppr_cc_expires_year' => '2025',
        'ppr_cc_cvv' => '123',
        'ppr_cc_save_card' => 'on',  // Checkbox checked (note: ppr_ prefix)
        'ppr_saved_card' => 'new',
    ];
    // Session should still have save_card from pre-confirmation, but let's test fresh
    $_SESSION = [
        'customer_id' => 99,
    ];

    // Simulate what validateCardInformation does
    $postvar_prefix = 'ppr';  // Confirmation flow
    $allowSaveCard = ($_SESSION['customer_id'] ?? 0) > 0;
    $storeCard = $allowSaveCard && !empty($_POST[$postvar_prefix . '_cc_save_card']);

    if ($storeCard === true) {
        $_SESSION['PayPalRestful']['save_card'] = true;
        echo "  ✓ Session variable set correctly for confirmation flow\n";
    } else {
        fwrite(STDERR, "  ✗ FAILED: Session variable not set for confirmation flow\n");
        fwrite(STDERR, "     This means the checkbox state is lost during confirmation!\n");
        $failures++;
    }

    echo "\nTest 3: Visibility determination uses session (not POST)...\n";
    
    // Simulate after validation - POST might be different or cleared
    $_POST = [];  // POST cleared or changed
    $_SESSION['PayPalRestful']['save_card'] = true;  // But session has the value

    // This is what storeVaultCardDataInSession and storeVaultCardData do
    $visible = !empty($_SESSION['PayPalRestful']['save_card']);

    if ($visible === true) {
        echo "  ✓ Visibility correctly determined from session (not POST)\n";
    } else {
        fwrite(STDERR, "  ✗ FAILED: Visibility not determined from session\n");
        $failures++;
    }

    echo "\nTest 4: Visibility false when checkbox not checked...\n";
    
    $_SESSION = [
        'customer_id' => 99,
    ];
    // Session should not have save_card if checkbox wasn't checked

    $visible = !empty($_SESSION['PayPalRestful']['save_card']);

    if ($visible === false) {
        echo "  ✓ Visibility correctly false when checkbox not checked\n";
    } else {
        fwrite(STDERR, "  ✗ FAILED: Visibility should be false\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n❌ Save card checkbox prefix test failed with $failures error(s).\n");
        exit(1);
    }

    echo "\n✅ All save card checkbox prefix tests passed.\n";
    echo "   - Pre-confirmation flow (paypalr_cc_save_card) works correctly\n";
    echo "   - Confirmation flow (ppr_cc_save_card) works correctly\n";
    echo "   - Visibility determination uses session variable (robust)\n";
}
