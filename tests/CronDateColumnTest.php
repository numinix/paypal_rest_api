<?php
declare(strict_types=1);

/**
 * Test to verify that paypalSavedCardRecurring methods use correct column name 'next_payment_date'
 * instead of 'date' in SQL queries.
 *
 * This test addresses the issue:
 * "MySQL error 1054: Unknown column 'date' in 'where clause'"
 * which occurred when running the cron job paypal_saved_card_recurring.php
 *
 * The fix changes SQL queries to use 'next_payment_date' which matches the actual
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
            
            // Check for the incorrect column name 'date' in WHERE clauses
            if (stripos($query, TABLE_SAVED_CREDIT_CARDS_RECURRING) !== false) {
                // Check if query incorrectly uses bare 'date' column (not date(), not next_payment_date, not date_added)
                if (preg_match('/\bdate\s*[<>=]/', $query) || 
                    preg_match('/AND\s+date\s+[<>=]/', $query) ||
                    preg_match('/SELECT\s+MAX\(\s*date\s*\)/', $query)) {
                    throw new Exception("Query uses incorrect column name 'date' instead of 'next_payment_date': " . $query);
                }
            }

            // Handle SHOW COLUMNS queries
            if (stripos($query, 'SHOW COLUMNS') !== false) {
                return new mockDbResult(0);
            }

            // Handle SELECT queries
            if (stripos($query, 'SELECT') !== false) {
                if (stripos($query, 'get_scheduled_payments') !== false || 
                    stripos($query, "status = 'scheduled'") !== false) {
                    return new mockDbResult(1, ['saved_credit_card_recurring_id' => 123]);
                }
                
                if (stripos($query, 'MAX(next_payment_date)') !== false) {
                    return new mockDbResult(1, ['last_success' => '2026-01-15']);
                }
                
                if (stripos($query, 'count(*)') !== false) {
                    return new mockDbResult(1, ['count' => 5]);
                }
                
                // For get_customer_subscriptions
                $fields = [
                    'saved_credit_card_recurring_id' => 1,
                    'products_id' => 100,
                    'status' => 'scheduled',
                    'next_payment_date' => '2026-02-15',
                    'date_added' => '2026-01-01'
                ];
                return new mockDbResult(1, $fields);
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

    fwrite(STDOUT, "=== Cron Date Column Test ===\n");
    fwrite(STDOUT, "Testing that cron-related methods use correct column name...\n\n");

    $failures = 0;

    // Load the class
    require_once DIR_FS_CATALOG . 'includes/classes/paypalSavedCardRecurring.php';

    // Test 1: Verify get_scheduled_payments() uses 'next_payment_date'
    fwrite(STDOUT, "Test 1: Verifying get_scheduled_payments() uses 'next_payment_date' in WHERE clause...\n");
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    
    try {
        $payments = $recurringObj->get_scheduled_payments();
        
        $queries = $db->getQueriesContaining(TABLE_SAVED_CREDIT_CARDS_RECURRING);
        $foundCorrect = false;
        foreach ($queries as $query) {
            if (stripos($query, 'next_payment_date') !== false) {
                $foundCorrect = true;
                fwrite(STDOUT, "✓ get_scheduled_payments() correctly uses 'next_payment_date' in WHERE clause\n");
                break;
            }
        }
        
        if (!$foundCorrect) {
            fwrite(STDERR, "✗ get_scheduled_payments() doesn't use 'next_payment_date'\n");
            $failures++;
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ get_scheduled_payments() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 2: Verify count_failed_payments() uses 'next_payment_date'
    fwrite(STDOUT, "\nTest 2: Verifying count_failed_payments() uses 'next_payment_date'...\n");
    $db = new mockDb();
    $GLOBALS['db'] = $db;
    
    $recurringObj = new paypalSavedCardRecurring();
    
    try {
        $reflection = new ReflectionClass($recurringObj);
        $method = $reflection->getMethod('count_failed_payments');
        $method->setAccessible(true);
        
        $count = $method->invoke($recurringObj, 123);
        
        $queries = $db->getQueriesContaining('MAX(');
        $foundCorrect = false;
        foreach ($queries as $query) {
            if (stripos($query, 'MAX(next_payment_date)') !== false) {
                $foundCorrect = true;
                fwrite(STDOUT, "✓ count_failed_payments() correctly uses 'MAX(next_payment_date)'\n");
                break;
            }
        }
        
        if (!$foundCorrect && count($queries) > 0) {
            fwrite(STDERR, "✗ count_failed_payments() doesn't use 'MAX(next_payment_date)'\n");
            $failures++;
        }
        
        // Check the second query also uses next_payment_date
        $allQueries = $db->queries;
        $foundSecondCorrect = false;
        foreach ($allQueries as $query) {
            if (stripos($query, "status = 'failed'") !== false && 
                stripos($query, 'next_payment_date >') !== false) {
                $foundSecondCorrect = true;
                fwrite(STDOUT, "✓ count_failed_payments() correctly uses 'next_payment_date' in WHERE clause\n");
                break;
            }
        }
        
        if (!$foundSecondCorrect) {
            fwrite(STDERR, "✗ count_failed_payments() WHERE clause doesn't use 'next_payment_date'\n");
            $failures++;
        }
    } catch (Exception $e) {
        fwrite(STDERR, "✗ count_failed_payments() test failed: " . $e->getMessage() . "\n");
        $failures++;
    }

    // Test 3: Verify customer_has_subscription() uses next_payment_date from array
    fwrite(STDOUT, "\nTest 3: Verifying customer_has_subscription() uses 'next_payment_date' from subscription array...\n");
    
    // Read the source code to verify the array access
    $sourceFile = DIR_FS_CATALOG . 'includes/classes/paypalSavedCardRecurring.php';
    $content = file_get_contents($sourceFile);
    
    if (preg_match('/customer_has_subscription.*?{.*?}.*?}/s', $content, $matches)) {
        $methodContent = $matches[0];
        if (strpos($methodContent, "['next_payment_date']") !== false || 
            strpos($methodContent, '["next_payment_date"]') !== false) {
            fwrite(STDOUT, "✓ customer_has_subscription() correctly uses \$subscription['next_payment_date']\n");
        } else {
            fwrite(STDERR, "✗ customer_has_subscription() doesn't use \$subscription['next_payment_date']\n");
            $failures++;
        }
    }

    // Summary
    fwrite(STDOUT, "\n=== Test Summary ===\n");
    if ($failures > 0) {
        fwrite(STDERR, sprintf("✗ Total failures: %d\n", $failures));
        exit(1);
    }

    fwrite(STDOUT, "✅ All cron date column tests passed!\n");
    fwrite(STDOUT, "All methods correctly use 'next_payment_date' instead of 'date'\n");
    exit(0);
}
