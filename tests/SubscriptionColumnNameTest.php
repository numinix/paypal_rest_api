<?php
declare(strict_types=1);

/**
 * Test to verify that subscription queries use the correct column name 'orders_products_id'
 * instead of 'original_orders_products_id' in the saved_credit_cards_recurring table.
 *
 * This test addresses the issue:
 * "MySQL error 1054: Unknown column 'original_orders_products_id' in 'where clause'"
 * which occurred when creating subscriptions.
 *
 * The fix changes all SQL queries to use 'orders_products_id' which matches the actual
 * column name in the saved_credit_cards_recurring table schema.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
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
    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }
    if (!defined('DIR_WS_CLASSES')) {
        define('DIR_WS_CLASSES', 'includes/classes/');
    }
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', 'test_');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS_RECURRING')) {
        define('TABLE_SAVED_CREDIT_CARDS_RECURRING', DB_PREFIX . 'saved_credit_cards_recurring');
    }
    if (!defined('TABLE_SAVED_CREDIT_CARDS')) {
        define('TABLE_SAVED_CREDIT_CARDS', DB_PREFIX . 'saved_credit_cards');
    }
    if (!defined('TABLE_CUSTOMERS')) {
        define('TABLE_CUSTOMERS', DB_PREFIX . 'customers');
    }
    if (!defined('TABLE_ORDERS_PRODUCTS')) {
        define('TABLE_ORDERS_PRODUCTS', DB_PREFIX . 'orders_products');
    }
    if (!defined('TABLE_ORDERS_PRODUCTS_ATTRIBUTES')) {
        define('TABLE_ORDERS_PRODUCTS_ATTRIBUTES', DB_PREFIX . 'orders_products_attributes');
    }
    if (!defined('TABLE_PRODUCTS')) {
        define('TABLE_PRODUCTS', DB_PREFIX . 'products');
    }
    if (!defined('TABLE_PAYPAL_RECURRING')) {
        define('TABLE_PAYPAL_RECURRING', DB_PREFIX . 'paypal_recurring');
    }
    if (!defined('TABLE_ORDERS')) {
        define('TABLE_ORDERS', DB_PREFIX . 'orders');
    }
    if (!defined('TABLE_PRODUCTS_OPTIONS')) {
        define('TABLE_PRODUCTS_OPTIONS', DB_PREFIX . 'products_options');
    }

    // Mock zen_db_input function
    if (!function_exists('zen_db_input')) {
        function zen_db_input($input) {
            return addslashes($input);
        }
    }

    // Mock database result
    class mockDbResult
    {
        private $recordCount;
        public $fields = [];

        public function __construct($recordCount, $fields = [])
        {
            $this->recordCount = $recordCount;
            $this->fields = $fields;
        }

        public function RecordCount()
        {
            return $this->recordCount;
        }
    }

    // Mock database class that validates SQL queries
    class mockDb
    {
        public $queries = [];

        public function Execute($query)
        {
            $this->queries[] = $query;
            
            // Check for the incorrect column name
            if (stripos($query, 'original_orders_products_id') !== false && 
                stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                throw new Exception("Query uses incorrect column name 'original_orders_products_id' instead of 'orders_products_id': " . $query);
            }

            // Handle SHOW COLUMNS queries
            if (stripos($query, 'SHOW COLUMNS') !== false) {
                return new mockDbResult(0);
            }

            // Handle SELECT queries from saved_credit_cards_recurring table
            if (stripos($query, 'SELECT') !== false && stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                $fields = [
                    'subscription_attributes_json' => '{}',
                    'billing_period' => 'MONTH',
                    'billing_frequency' => 1,
                    'total_billing_cycles' => 12,
                    'currency_code' => 'USD'
                ];
                return new mockDbResult(1, $fields);
            }

            // Handle COUNT queries
            if (stripos($query, 'COUNT(*)') !== false) {
                return new mockDbResult(1, ['num_cycles' => 3]);
            }

            // Handle other SELECT queries
            if (stripos($query, 'SELECT') !== false) {
                return new mockDbResult(0);
            }

            return new mockDbResult(0);
        }

        public function getQueriesContaining($needle)
        {
            return array_filter($this->queries, function($query) use ($needle) {
                return stripos($query, $needle) !== false;
            });
        }
    }

    // Mock classes
    if (!class_exists('order')) {
        class order {}
    }
    if (!class_exists('order_total')) {
        class order_total {}
    }
    if (!class_exists('shipping')) {
        class shipping {}
    }
    if (!class_exists('payment')) {
        class payment {}
    }
    if (!class_exists('shopping_cart')) {
        class shopping_cart {}
    }

    // Create mock files to avoid require errors
    $mockFiles = [
        DIR_FS_CATALOG . DIR_WS_CLASSES . 'order.php',
        DIR_FS_CATALOG . DIR_WS_CLASSES . 'order_total.php',
        DIR_FS_CATALOG . DIR_WS_CLASSES . 'shipping.php',
        DIR_FS_CATALOG . DIR_WS_CLASSES . 'payment.php',
        DIR_FS_CATALOG . DIR_WS_CLASSES . 'shopping_cart.php',
    ];
    
    foreach ($mockFiles as $file) {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($file)) {
            file_put_contents($file, '<?php // Mock file for testing');
        }
    }

    $failures = 0;

    // Load the class
    require_once DIR_FS_CATALOG . 'includes/classes/paypalSavedCardRecurring.php';

    // Test 1: Verify get_attributes() uses correct column name
    fwrite(STDOUT, "Test 1: Verifying get_attributes() uses 'orders_products_id' instead of 'original_orders_products_id'...\n");
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    $reflection = new ReflectionClass($recurringObj);
    
    $method = $reflection->getMethod('get_attributes');
    $method->setAccessible(true);
    
    try {
        $method->invoke($recurringObj, 12345);
        
        // Check if any query used the correct column name
        $queries = $db->getQueriesContaining(TABLE_SAVED_CREDIT_CARDS_RECURRING);
        $foundCorrect = false;
        foreach ($queries as $query) {
            if (stripos($query, 'SELECT') === 0 && stripos($query, 'orders_products_id = ') !== false) {
                $foundCorrect = true;
                fwrite(STDOUT, "✓ get_attributes() correctly uses 'orders_products_id'\n");
                break;
            }
        }
        if (!$foundCorrect && count($queries) > 0) {
            fwrite(STDERR, "✗ get_attributes() query doesn't use 'orders_products_id'\n");
            $failures++;
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ get_attributes() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 2: Verify get_domain() uses correct column name
    fwrite(STDOUT, "\nTest 2: Verifying get_domain() uses 'orders_products_id' instead of 'original_orders_products_id'...\n");
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    $reflection = new ReflectionClass($recurringObj);
    
    $method = $reflection->getMethod('get_domain');
    $method->setAccessible(true);
    
    try {
        $method->invoke($recurringObj, 12345);
        
        // Check if any query used the correct column name
        $queries = $db->getQueriesContaining(TABLE_SAVED_CREDIT_CARDS_RECURRING);
        $foundCorrect = false;
        foreach ($queries as $query) {
            if (stripos($query, 'SELECT') === 0 && stripos($query, 'orders_products_id = ') !== false) {
                $foundCorrect = true;
                fwrite(STDOUT, "✓ get_domain() correctly uses 'orders_products_id'\n");
                break;
            }
        }
        if (!$foundCorrect && count($queries) > 0) {
            fwrite(STDERR, "✗ get_domain() query doesn't use 'orders_products_id'\n");
            $failures++;
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ get_domain() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 3: Verify build_subscription_scope_sql() uses correct column name
    fwrite(STDOUT, "\nTest 3: Verifying build_subscription_scope_sql() uses 'orders_products_id' instead of 'original_orders_products_id'...\n");
    $recurringObj = new paypalSavedCardRecurring();
    $reflection = new ReflectionClass($recurringObj);
    
    $method = $reflection->getMethod('build_subscription_scope_sql');
    $method->setAccessible(true);
    
    try {
        $whereClause = $method->invoke($recurringObj, ['original_orders_products_id' => 12345]);
        
        if (stripos($whereClause, 'orders_products_id = ') !== false) {
            fwrite(STDOUT, "✓ build_subscription_scope_sql() correctly uses 'orders_products_id'\n");
        } else {
            fwrite(STDERR, "✗ build_subscription_scope_sql() doesn't use 'orders_products_id'. Returned: " . $whereClause . "\n");
            $failures++;
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ build_subscription_scope_sql() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All subscription column name tests passed\n");
    exit(0);
}
