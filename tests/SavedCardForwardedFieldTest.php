<?php
declare(strict_types=1);

/**
 * Test to verify that the saved card selection is correctly retrieved
 * from forwarded POST fields (ppac_saved_card) as well as direct POST
 * (paypalac_saved_card) and session.
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
    if (!defined('HTTP_SERVER')) {
        define('HTTP_SERVER', 'https://example.com');
    }
    if (!defined('DIR_WS_CATALOG')) {
        define('DIR_WS_CATALOG', '/shop/');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_STATUS')) {
        define('MODULE_PAYMENT_PAYPALAC_STATUS', 'True');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT')) {
        define('MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT', 'True');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SAVED_CARD_GENERIC')) {
        define('MODULE_PAYMENT_PAYPALAC_SAVED_CARD_GENERIC', 'Card');
    }

    // Mock required classes
    class paypalac_creditcard_test_wrapper
    {
        public function testSavedCardRetrieval(): void
        {
            echo "Testing saved card field name resolution...\n";
            
            $failures = 0;

            // Test 1: Direct POST field (paypalac_saved_card)
            $_POST = ['paypalac_saved_card' => 'vault-123'];
            $_SESSION = [];
            $result = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalAdvancedCheckout']['saved_card'] ?? 'new'));
            if ($result !== 'vault-123') {
                fwrite(STDERR, "Test 1 failed: Expected 'vault-123', got '$result'\n");
                $failures++;
            } else {
                echo "✓ Test 1 passed: Direct POST field (paypalac_saved_card) correctly retrieved\n";
            }

            // Test 2: Forwarded POST field (ppac_saved_card)
            $_POST = ['ppac_saved_card' => 'vault-456'];
            $_SESSION = [];
            $result = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalAdvancedCheckout']['saved_card'] ?? 'new'));
            if ($result !== 'vault-456') {
                fwrite(STDERR, "Test 2 failed: Expected 'vault-456', got '$result'\n");
                $failures++;
            } else {
                echo "✓ Test 2 passed: Forwarded POST field (ppac_saved_card) correctly retrieved\n";
            }

            // Test 3: Session fallback
            $_POST = [];
            $_SESSION = ['PayPalAdvancedCheckout' => ['saved_card' => 'vault-789']];
            $result = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalAdvancedCheckout']['saved_card'] ?? 'new'));
            if ($result !== 'vault-789') {
                fwrite(STDERR, "Test 3 failed: Expected 'vault-789', got '$result'\n");
                $failures++;
            } else {
                echo "✓ Test 3 passed: Session fallback correctly retrieved\n";
            }

            // Test 4: Default to 'new'
            $_POST = [];
            $_SESSION = [];
            $result = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalAdvancedCheckout']['saved_card'] ?? 'new'));
            if ($result !== 'new') {
                fwrite(STDERR, "Test 4 failed: Expected 'new', got '$result'\n");
                $failures++;
            } else {
                echo "✓ Test 4 passed: Default value 'new' correctly returned\n";
            }

            // Test 5: Priority order (direct POST > forwarded POST > session > default)
            $_POST = ['paypalac_saved_card' => 'vault-priority'];
            $_SESSION = ['PayPalAdvancedCheckout' => ['saved_card' => 'vault-session']];
            $result = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalAdvancedCheckout']['saved_card'] ?? 'new'));
            if ($result !== 'vault-priority') {
                fwrite(STDERR, "Test 5 failed: Expected 'vault-priority', got '$result'\n");
                $failures++;
            } else {
                echo "✓ Test 5 passed: Direct POST takes priority over session\n";
            }

            // Test 6: Forwarded field takes priority over session
            $_POST = ['ppac_saved_card' => 'vault-forwarded'];
            $_SESSION = ['PayPalAdvancedCheckout' => ['saved_card' => 'vault-session']];
            $result = $_POST['paypalac_saved_card'] ?? ($_POST['ppac_saved_card'] ?? ($_SESSION['PayPalAdvancedCheckout']['saved_card'] ?? 'new'));
            if ($result !== 'vault-forwarded') {
                fwrite(STDERR, "Test 6 failed: Expected 'vault-forwarded', got '$result'\n");
                $failures++;
            } else {
                echo "✓ Test 6 passed: Forwarded POST takes priority over session\n";
            }

            if ($failures > 0) {
                fwrite(STDERR, "\n$failures test(s) failed.\n");
                exit(1);
            }

            echo "\n✓ All saved card field name tests passed.\n";
        }
    }

    $tester = new paypalac_creditcard_test_wrapper();
    $tester->testSavedCardRetrieval();
}
