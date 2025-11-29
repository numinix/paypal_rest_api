<?php
declare(strict_types=1);

/**
 * Test to verify that TABLE_PAYPAL_VAULT constant is defined before use
 * in PayPalCommon::getVaultedCardsForCustomer()
 *
 * This test addresses the issue:
 * "PHP Fatal error: Uncaught Error: Undefined constant TABLE_PAYPAL_VAULT"
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
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
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'test_');
    }
    // Note: TABLE_PAYPAL_VAULT is intentionally NOT defined here to test that
    // the getVaultedCardsForCustomer method defines it itself

    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Helpers.php';
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalRestful/Common/Logger.php';

    class MockDbRecord
    {
        public bool $EOF = true;
        public array $fields = [];
        
        public function MoveNext(): void
        {
        }
    }

    class MockDb
    {
        public function Execute($query)
        {
            return new MockDbRecord();
        }
    }

    // PayPal mock classes to satisfy dependencies
    class MockPaymentModule
    {
        public $code = 'paypalr_creditcard';
    }

    $db = new MockDb();

    // Manually include PayPalCommon since it needs special handling
    // We need to mock the use statements
    class MockPayPalRestfulApi {}
    class MockVaultManager {}
    
    // Define classes that might be referenced
    if (!class_exists('PayPalRestful\Api\PayPalRestfulApi')) {
        class_alias('MockPayPalRestfulApi', 'PayPalRestful\Api\PayPalRestfulApi');
    }
    if (!class_exists('PayPalRestful\Common\VaultManager')) {
        class_alias('MockVaultManager', 'PayPalRestful\Common\VaultManager');
    }
}

namespace PayPalRestful\Common {
    // Define Helpers if not already loaded
    if (!class_exists('PayPalRestful\Common\Helpers')) {
        class Helpers {
            public static function getEnvironment(): string {
                return 'sandbox';
            }
        }
    }
    
    if (!class_exists('PayPalRestful\Common\Logger')) {
        class Logger {
            public function __construct($env = null) {}
            public function write($msg, $level = null) {}
        }
    }
}

namespace PayPalRestful\Api {
    if (!class_exists('PayPalRestful\Api\PayPalRestfulApi')) {
        class PayPalRestfulApi {
            public function __construct($clientId = null, $secret = null, $env = null) {}
        }
    }
}

namespace {
    // Now load the PayPalCommon class for testing
    require_once DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php';

    $failures = 0;

    // Test: Verify that getVaultedCardsForCustomer doesn't cause an undefined constant error
    // even when TABLE_PAYPAL_VAULT is not pre-defined
    try {
        $paymentModule = new MockPaymentModule();
        $paypalCommon = new PayPalCommon($paymentModule);
        
        // This should NOT throw an "Undefined constant" error
        $cards = $paypalCommon->getVaultedCardsForCustomer(12345, true);
        
        if (!is_array($cards)) {
            fwrite(STDERR, "Expected getVaultedCardsForCustomer to return an array\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ getVaultedCardsForCustomer returns array without undefined constant error\n");
        }
        
        // Verify the constant is now defined
        if (!defined('TABLE_PAYPAL_VAULT')) {
            fwrite(STDERR, "Expected TABLE_PAYPAL_VAULT to be defined after method call\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ TABLE_PAYPAL_VAULT constant is properly defined\n");
        }
        
        // Verify the constant has the correct value
        if (TABLE_PAYPAL_VAULT !== DB_PREFIX . 'paypal_vault') {
            fwrite(STDERR, sprintf(
                "Expected TABLE_PAYPAL_VAULT to be '%s', got '%s'\n",
                DB_PREFIX . 'paypal_vault',
                TABLE_PAYPAL_VAULT
            ));
            $failures++;
        } else {
            fwrite(STDOUT, "✓ TABLE_PAYPAL_VAULT has correct value: " . TABLE_PAYPAL_VAULT . "\n");
        }
        
    } catch (Error $e) {
        if (strpos($e->getMessage(), 'Undefined constant') !== false) {
            fwrite(STDERR, "CRITICAL: Undefined constant error: " . $e->getMessage() . "\n");
            $failures++;
        } else {
            // Re-throw unexpected errors
            throw $e;
        }
    }

    // Test: Verify method returns empty array for invalid customer ID
    try {
        $paymentModule = new MockPaymentModule();
        $paypalCommon = new PayPalCommon($paymentModule);
        
        $cards = $paypalCommon->getVaultedCardsForCustomer(0, true);
        
        if ($cards !== []) {
            fwrite(STDERR, "Expected empty array for customer_id = 0\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Returns empty array for invalid customer ID (0)\n");
        }
        
        $cards = $paypalCommon->getVaultedCardsForCustomer(-1, true);
        
        if ($cards !== []) {
            fwrite(STDERR, "Expected empty array for customer_id = -1\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Returns empty array for invalid customer ID (-1)\n");
        }
        
    } catch (Error $e) {
        fwrite(STDERR, "Unexpected error: " . $e->getMessage() . "\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\nTotal failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✓ All TABLE_PAYPAL_VAULT constant tests passed\n");
    exit(0);
}
