<?php
declare(strict_types=1);

/**
 * Test to verify that schedule_payment() uses the correct column name 'next_payment_date'
 * instead of 'date' in the saved_credit_cards_recurring table.
 *
 * This test addresses the issue:
 * "MySQL error 1054: Unknown column 'date' in 'field list'"
 * which occurred when creating subscriptions from orders with saved cards.
 *
 * The fix changes the SQL INSERT to use 'next_payment_date' which matches the actual
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
        
        public function MoveNext()
        {
            $this->EOF = true;
        }
    }

    // Mock database class that validates SQL queries and captures perform() calls
    class mockDb
    {
        public $queries = [];
        public $performCalls = [];
        private $lastInsertId = 1;

        public function Execute($query)
        {
            $this->queries[] = $query;
            
            // Handle validation query from validate_saved_card
            if (stripos($query, 'SELECT \'true\' as valid FROM') !== false) {
                $fields = ['valid' => 'true'];
                return new mockDbResult(1, $fields);
            }
            
            // Handle SELECT queries from saved_credit_cards table
            if (stripos($query, 'SELECT') !== false && stripos($query, TABLE_SAVED_CREDIT_CARDS) !== false) {
                $fields = [
                    'saved_credit_card_id' => 1,
                    'customers_id' => 1
                ];
                return new mockDbResult(1, $fields);
            }

            // Handle SELECT queries from orders_products table
            if (stripos($query, 'SELECT') !== false && stripos($query, TABLE_ORDERS_PRODUCTS) !== false) {
                $fields = [
                    'orders_id' => 1,
                    'products_id' => 181,
                    'products_name' => 'Test Product',
                    'products_model' => 'TEST-MODEL'
                ];
                return new mockDbResult(1, $fields);
            }

            // Handle other SELECT queries
            if (stripos($query, 'SELECT') !== false) {
                return new mockDbResult(0);
            }

            return new mockDbResult(0);
        }

        public function perform($table, $data_array, $action = 'insert', $where = '')
        {
            // Capture the perform call for analysis
            $this->performCalls[] = [
                'table' => $table,
                'data' => $data_array,
                'action' => $action,
                'where' => $where
            ];

            // Validate that we're NOT using 'date' field for saved_credit_cards_recurring
            if ($table === TABLE_SAVED_CREDIT_CARDS_RECURRING) {
                foreach ($data_array as $field) {
                    if (isset($field['fieldName']) && $field['fieldName'] === 'date') {
                        throw new Exception(
                            "perform() call uses incorrect column name 'date' instead of 'next_payment_date' " .
                            "in table " . TABLE_SAVED_CREDIT_CARDS_RECURRING
                        );
                    }
                }
            }

            return true;
        }

        public function insert_ID()
        {
            return $this->lastInsertId++;
        }

        public function getPerformCallsForTable($table)
        {
            return array_filter($this->performCalls, function($call) use ($table) {
                return $call['table'] === $table;
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

    // Test: Verify schedule_payment() uses 'next_payment_date' instead of 'date'
    fwrite(STDOUT, "Test: Verifying schedule_payment() uses 'next_payment_date' instead of 'date'...\n");
    
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    
    try {
        // Call schedule_payment with test data
        $amount = '80.01';
        $date = '2026-02-04';
        $saved_credit_card_id = 1;
        $original_orders_products_id = 33927;
        $comments = 'Subscription created from order #26373';
        $metadata = [
            'products_id' => 181,
            'products_name' => 'NEW NCRS Membership Canada',
            'products_model' => '',
            'currency_code' => 'USD',
            'billing_period' => 'WEEK',
            'billing_frequency' => 1,
            'total_billing_cycles' => 1,
            'domain' => '',
            'subscription_attributes_json' => '{}'
        ];

        $result = $recurringObj->schedule_payment(
            $amount,
            $date,
            $saved_credit_card_id,
            $original_orders_products_id,
            $comments,
            $metadata
        );

        // Check the perform calls
        $performCalls = $db->getPerformCallsForTable(TABLE_SAVED_CREDIT_CARDS_RECURRING);
        
        if (count($performCalls) === 0) {
            fwrite(STDERR, "✗ No perform() calls found for " . TABLE_SAVED_CREDIT_CARDS_RECURRING . "\n");
            $failures++;
        } else {
            $call = reset($performCalls);
            $fieldNames = array_column($call['data'], 'fieldName');
            
            // Check if 'next_payment_date' is used (correct)
            if (in_array('next_payment_date', $fieldNames)) {
                fwrite(STDOUT, "✓ schedule_payment() correctly uses 'next_payment_date'\n");
            } else {
                fwrite(STDERR, "✗ schedule_payment() doesn't use 'next_payment_date'\n");
                fwrite(STDERR, "  Field names found: " . implode(', ', $fieldNames) . "\n");
                $failures++;
            }

            // Check if 'date' is NOT used (incorrect)
            if (in_array('date', $fieldNames)) {
                fwrite(STDERR, "✗ schedule_payment() incorrectly uses 'date' field name\n");
                $failures++;
            }
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ schedule_payment() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    if ($failures > 0) {
        fwrite(STDERR, sprintf("\n✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "\n✅ All schedule payment date column tests passed\n");
    exit(0);
}
