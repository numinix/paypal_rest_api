<?php
declare(strict_types=1);

/**
 * Test to verify that TABLE_PAYPAL_VAULT constant is loaded from extra_datafiles
 * and used correctly in PayPalCommon::getVaultedCardsForCustomer()
 *
 * This test addresses the issue:
 * "PHP Fatal error: Uncaught Error: Undefined constant TABLE_PAYPAL_VAULT"
 *
 * The fix adds the constant definition to includes/extra_datafiles/ppac_database_tables.php
 * which Zen Cart loads site-wide automatically.
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

    // Load the extra_datafiles that defines table constants (simulating Zen Cart's auto-load behavior)
    require_once DIR_FS_CATALOG . 'includes/extra_datafiles/ppac_database_tables.php';

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
        public $code = 'paypalac_creditcard';
    }

    $db = new MockDb();

    // Manually include PayPalCommon since it needs special handling
    // We need to mock the use statements
    class MockPayPalRestfulApi {}
    class MockVaultManager {
        public static function getCustomerVaultedCards(int $customers_id, bool $activeOnly = true): array {
            if ($customers_id <= 0) {
                return [];
            }
            return [];
        }
    }
    
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

    // Test 1: Verify the extra_datafiles properly defines TABLE_PAYPAL_VAULT
    if (!defined('TABLE_PAYPAL_VAULT')) {
        fwrite(STDERR, "CRITICAL: TABLE_PAYPAL_VAULT not defined by extra_datafiles\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ TABLE_PAYPAL_VAULT is defined by extra_datafiles\n");
    }

    // Test 2: Verify the constant has the correct value
    if (defined('TABLE_PAYPAL_VAULT') && TABLE_PAYPAL_VAULT !== DB_PREFIX . 'paypal_vault') {
        fwrite(STDERR, sprintf(
            "Expected TABLE_PAYPAL_VAULT to be '%s', got '%s'\n",
            DB_PREFIX . 'paypal_vault',
            TABLE_PAYPAL_VAULT
        ));
        $failures++;
    } else {
        fwrite(STDOUT, "✓ TABLE_PAYPAL_VAULT has correct value: " . TABLE_PAYPAL_VAULT . "\n");
    }

    // Test 3: Verify all PayPal table constants are defined
    $required_constants = [
        'TABLE_PAYPAL',
        'TABLE_PAYPAL_VAULT',
        'TABLE_PAYPAL_SUBSCRIPTIONS',
        'TABLE_PAYPAL_WEBHOOKS',
    ];
    foreach ($required_constants as $const) {
        if (!defined($const)) {
            fwrite(STDERR, "CRITICAL: $const not defined by extra_datafiles\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ $const is defined\n");
        }
    }

    // Test 4: Verify that getVaultedCardsForCustomer works with the constant
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
        
    } catch (Error $e) {
        if (strpos($e->getMessage(), 'Undefined constant') !== false) {
            fwrite(STDERR, "CRITICAL: Undefined constant error: " . $e->getMessage() . "\n");
            $failures++;
        } else {
            // Re-throw unexpected errors
            throw $e;
        }
    }

    // Test 5: Verify method returns empty array for invalid customer ID
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
