<?php
require_once __DIR__ . '/../../includes/modules/pages/my_subscriptions/functions.php';

if (!function_exists('zen_db_input')) {
    function zen_db_input($string)
    {
        return addslashes($string);
    }
}

class FakeRecordset
{
    public $EOF;
    public $fields;

    public function __construct(array $fields = array(), $eof = true)
    {
        $this->fields = $fields;
        $this->EOF = $eof;
    }

    public function MoveNext()
    {
        $this->EOF = true;
    }
}

class FakeDB
{
    public $queries = array();
    public $results = array();

    public function Execute($sql)
    {
        $this->queries[] = $sql;
        if (!empty($this->results)) {
            return array_shift($this->results);
        }

        return new FakeRecordset();
    }
}

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . ' Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true));
    }
}

function assert_true($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

try {
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', '');
    }

    $db = new FakeDB();
    $GLOBALS['db'] = $db;

    zen_paypal_subscription_cache_memory_forget();

    $customerId = 321;
    $now = time();

    $subscriptionRows = array(
        array(
            'subscription_id' => 1,
            'customers_id' => $customerId,
            'profile_id' => 'A1',
            'products_id' => 10,
            'orders_id' => 200,
            'status' => 'Active',
        ),
        array(
            'subscription_id' => 2,
            'customers_id' => $customerId,
            'profile_id' => 'B2',
            'products_id' => 11,
            'orders_id' => 201,
            'status' => 'Suspended',
        ),
        array(
            'subscription_id' => 3,
            'customers_id' => $customerId,
            'profile_id' => 'C3',
            'products_id' => 12,
            'orders_id' => 202,
            'status' => 'Active',
        ),
    );

    $cacheMemory =& zen_paypal_subscription_cache_memory_storage();
    $cacheMemory[$customerId . ':A1'] = array(
        'customers_id' => $customerId,
        'profile_id' => 'A1',
        'status' => 'Active',
        'profile_source' => 'rest',
        'profile_data' => json_encode(array('PROFILEID' => 'A1', 'STATUS' => 'Active')),
        'refreshed_at' => date('Y-m-d H:i:s', $now - 60),
    );
    $cacheMemory[$customerId . ':B2'] = array(
        'customers_id' => $customerId,
        'profile_id' => 'B2',
        'status' => 'Suspended',
        'profile_source' => 'legacy',
        'profile_data' => json_encode(array('PROFILEID' => 'B2', 'STATUS' => 'Suspended')),
        'refreshed_at' => date('Y-m-d H:i:s', $now - 7200),
    );

    $results = array();
    foreach ($subscriptionRows as $row) {
        $results[] = zen_paypal_subscription_admin_resolve_cached_profile(
            $row,
            array()
        );
    }

    assert_true(empty($results[0]['refresh_pending']), 'Fresh cache should not be marked pending.');
    assert_same('Active', $results[0]['profile_data']['status'], 'Fresh cache should expose cached status.');

    assert_true(!empty($results[1]['refresh_pending']), 'Stale cache should now be marked pending.');
    assert_same('stale_cache', $results[1]['subscription']['refresh_pending_reason'], 'Stale cache should record pending reason.');
    assert_same('Suspended', $results[1]['profile_data']['status'], 'Stale row should expose cached status.');

    assert_true(!empty($results[2]['refresh_pending']), 'Missing cache should remain pending.');
    assert_same('missing_cache', $results[2]['subscription']['refresh_pending_reason'], 'Missing cache should note pending reason.');
    assert_same('Active', $results[2]['profile_data']['status'], 'Missing cache should fall back to subscription status.');

    echo "All integration assertions passed.\n";
    exit(0);
} catch (Exception $exception) {
    fwrite(STDERR, 'Test failure: ' . $exception->getMessage() . "\n");
    exit(1);
}
