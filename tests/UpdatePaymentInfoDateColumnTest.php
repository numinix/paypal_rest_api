<?php
declare(strict_types=1);

/**
 * Test to verify that update_payment_info() uses the correct column name 'next_payment_date'
 * instead of 'date' in the saved_credit_cards_recurring table UPDATE query.
 *
 * This test addresses the issue:
 * "MySQL error 1054: Unknown column 'date' in 'field list'"
 * which occurred when updating saved card subscription payment information via admin.
 *
 * The fix changes the SQL UPDATE to use 'next_payment_date' which matches the actual
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
    if (!defined('MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL')) {
        define('MODULE_PAYMENT_PAYPALSAVEDCARD_ERROR_NOTIFICATION_EMAIL', 'test@example.com');
    }

    // Mock zen_db_input function
    if (!function_exists('zen_db_input')) {
        function zen_db_input($input) {
            return addslashes($input);
        }
    }
    
    // Mock zen_mail function
    if (!function_exists('zen_mail')) {
        function zen_mail($to, $subject, $message, $from = '', $fromName = '', $headers = '') {
            return true;
        }
    }

    // Mock database result
    class mockDbResult
    {
        private $recordCount;
        public $fields = [];
        public $EOF = true;

        public function __construct($recordCount, $fields = [])
        {
            $this->recordCount = $recordCount;
            $this->fields = $fields;
            $this->EOF = ($recordCount === 0);
        }

        public function RecordCount()
        {
            return $this->recordCount;
        }
        
        public function MoveNext(): void
        {
            $this->EOF = true;
        }
    }

    // Mock database class that validates SQL queries
    class mockDb
    {
        public $queries = [];

        public function Execute($query)
        {
            $this->queries[] = $query;
            
            // Check for the incorrect column name in UPDATE statements
            if (stripos($query, 'UPDATE') !== false && 
                stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                // Check if query incorrectly uses 'date =' instead of 'next_payment_date ='
                if (preg_match('/\bdate\s*=\s*/', $query) && 
                    !preg_match('/next_payment_date\s*=/', $query) &&
                    !preg_match('/date_added\s*=/', $query)) {
                    throw new Exception("UPDATE query uses incorrect column name 'date' instead of 'next_payment_date': " . $query);
                }
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
                    'currency_code' => 'USD',
                    'next_payment_date' => '2026-02-01',
                    'date_added' => '2026-01-01 00:00:00'
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

    fwrite(STDOUT, "=== Update Payment Info Date Column Test ===\n");
    fwrite(STDOUT, "Testing that update_payment_info() uses correct column name...\n\n");

    $failures = 0;

    // Load the class
    require_once DIR_FS_CATALOG . 'includes/classes/paypalSavedCardRecurring.php';

    // Test 1: Verify update_payment_info() uses 'next_payment_date' instead of 'date'
    fwrite(STDOUT, "Test 1: Verifying update_payment_info() uses 'next_payment_date' for date updates...\n");
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    
    try {
        // Call update_payment_info with a date parameter
        $updateData = [
            'date' => '2026-02-01',
            'amount' => '99.99',
            'comments' => 'Test update'
        ];
        $recurringObj->update_payment_info(123, $updateData);
        
        // Check if any UPDATE query was executed
        $updateQueries = $db->getQueriesContaining('UPDATE');
        if (count($updateQueries) === 0) {
            fwrite(STDERR, "✗ No UPDATE queries were executed\n");
            $failures++;
        } else {
            // Check if the query uses 'next_payment_date' and not bare 'date'
            $foundCorrect = false;
            foreach ($updateQueries as $query) {
                if (stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                    if (preg_match('/next_payment_date\s*=/', $query)) {
                        $foundCorrect = true;
                        fwrite(STDOUT, "✓ update_payment_info() correctly uses 'next_payment_date' column\n");
                        break;
                    }
                }
            }
            
            if (!$foundCorrect) {
                fwrite(STDERR, "✗ update_payment_info() doesn't use 'next_payment_date' in UPDATE\n");
                fwrite(STDERR, "  Queries executed: " . print_r($updateQueries, true) . "\n");
                $failures++;
            }
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ update_payment_info() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 2: Verify update_payment_info() with order_id also uses correct column
    fwrite(STDOUT, "\nTest 2: Verifying update_payment_info() with order_id uses 'next_payment_date'...\n");
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    
    try {
        $updateData = [
            'order_id' => 456,
            'date' => '2026-03-01',
            'amount' => '150.00'
        ];
        $recurringObj->update_payment_info(789, $updateData);
        
        $updateQueries = $db->getQueriesContaining('UPDATE');
        if (count($updateQueries) === 0) {
            fwrite(STDERR, "✗ No UPDATE queries were executed\n");
            $failures++;
        } else {
            $foundCorrect = false;
            foreach ($updateQueries as $query) {
                if (stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                    if (preg_match('/next_payment_date\s*=/', $query)) {
                        $foundCorrect = true;
                        fwrite(STDOUT, "✓ update_payment_info() with order_id correctly uses 'next_payment_date'\n");
                        break;
                    }
                }
            }
            
            if (!$foundCorrect) {
                fwrite(STDERR, "✗ update_payment_info() with order_id doesn't use 'next_payment_date'\n");
                $failures++;
            }
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ update_payment_info() with order_id test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "✅ All update payment info date column tests passed!\n");
    exit(0);
}
