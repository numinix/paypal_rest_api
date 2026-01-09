<?php
require_once __DIR__ . '/../../includes/modules/pages/my_subscriptions/functions.php';

if (!function_exists('zen_db_input')) {
    function zen_db_input($string)
    {
        return addslashes($string);
    }
}

class DummyRecordset
{
    public $EOF = true;
    public $fields = array();

    public function MoveNext()
    {
        $this->EOF = true;
    }
}

class DummyDB
{
    public $queries = array();

    public function Execute($sql)
    {
        $this->queries[] = $sql;
        return new DummyRecordset();
    }
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function run_cache_pending_tests()
{
    zen_paypal_subscription_cache_memory_forget();

    $GLOBALS['db'] = new DummyDB();

    $now = time();
    $memory =& zen_paypal_subscription_cache_memory_storage();
    $memory['100:PROFILE_FRESH'] = array(
        'customers_id' => 100,
        'profile_id' => 'PROFILE_FRESH',
        'status' => 'Active',
        'profile_source' => 'rest',
        'profile_data' => json_encode(array('STATUS' => 'Active')),
        'refreshed_at' => date('Y-m-d H:i:s', $now),
    );
    $memory['100:PROFILE_STALE'] = array(
        'customers_id' => 100,
        'profile_id' => 'PROFILE_STALE',
        'status' => 'Active',
        'profile_source' => 'rest',
        'profile_data' => json_encode(array('STATUS' => 'Active')),
        'refreshed_at' => date('Y-m-d H:i:s', $now - 3600),
    );

    $commonOptions = array(
        'refresh_pending_message' => 'Pending message',
        'cache_ttl' => 300,
    );

    $fresh = array(
        'customers_id' => 100,
        'profile_id' => 'PROFILE_FRESH',
        'profile' => array(),
    );
    $freshResult = zen_paypal_subscription_consume_cache_for_subscription($fresh, $commonOptions);
    assertTrue($freshResult['refresh_pending'] === false, 'Fresh cache should not require a refresh.');
    assertTrue(empty($fresh['refresh_pending_reason']), 'Fresh cache should not include a pending reason.');
    assertTrue(isset($fresh['status']) && $fresh['status'] === 'Active', 'Fresh cache should apply cached status.');
    assertTrue(isset($fresh['refreshed_at']) && $fresh['refreshed_at'] !== '', 'Fresh cache should expose refreshed_at.');

    $stale = array(
        'customers_id' => 100,
        'profile_id' => 'PROFILE_STALE',
        'profile' => array(),
    );
    $staleResult = zen_paypal_subscription_consume_cache_for_subscription($stale, $commonOptions);
    assertTrue($staleResult['refresh_pending'] === true, 'Stale cache should now be marked pending.');
    assertTrue(!empty($stale['refresh_pending']), 'Stale cache should flag refresh_pending.');
    assertTrue($stale['refresh_pending_reason'] === 'stale_cache', 'Stale cache should note the pending reason.');

    $missing = array(
        'customers_id' => 100,
        'profile_id' => 'PROFILE_MISSING',
        'profile' => array(),
    );
    $missingResult = zen_paypal_subscription_consume_cache_for_subscription($missing, $commonOptions);
    assertTrue($missingResult['refresh_pending'] === true, 'Missing cache should remain pending.');
    assertTrue(!empty($missing['refresh_pending']) && $missing['refresh_pending_reason'] === 'missing_cache', 'Missing cache should note the pending reason.');
    assertTrue(isset($missing['refresh_pending_message']) && $missing['refresh_pending_message'] === 'Pending message', 'Pending message should propagate to subscription.');
    assertTrue(empty($missing['refreshed_at']), 'Missing cache should not fabricate refreshed_at values.');
}

try {
    run_cache_pending_tests();
    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test failure: ' . $e->getMessage() . "\n");
    exit(1);
}
