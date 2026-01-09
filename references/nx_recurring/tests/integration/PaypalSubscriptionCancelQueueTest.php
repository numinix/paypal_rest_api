<?php
require_once __DIR__ . '/../../includes/modules/pages/my_subscriptions/functions.php';
require_once __DIR__ . '/../../includes/modules/pages/my_subscriptions/debug.php';
require_once __DIR__ . '/../../includes/classes/paypal/PayPalProfileManager.php';

if (!defined('MY_SUBSCRIPTIONS_DEBUG')) {
    define('MY_SUBSCRIPTIONS_DEBUG', false);
}

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

    public function Execute($sql)
    {
        $this->queries[] = $sql;
        return new FakeRecordset();
    }
}

class StubGateway implements PayPalProfileGatewayInterface
{
    public $cancelCalls = array();

    public function cancelProfile(array $subscription, $note = '', array $context = array())
    {
        $this->cancelCalls[] = array('subscription' => $subscription, 'note' => $note, 'context' => $context);
        return array('success' => true, 'message' => '', 'status' => 'Cancelled');
    }

    public function suspendProfile(array $subscription, $note = '', array $context = array())
    {
        return array('success' => true);
    }

    public function reactivateProfile(array $subscription, $note = '', array $context = array())
    {
        return array('success' => true);
    }

    public function getProfileStatus(array $subscription, array $context = array())
    {
        return array('success' => true, 'status' => 'Cancelled', 'profile' => array('STATUS' => 'Cancelled'), 'profile_source' => 'legacy');
    }

    public function updateBillingCycles(array $subscription, array $billingCycles, array $context = array())
    {
        return array('success' => true);
    }

    public function updatePaymentSource(array $subscription, array $paymentSource, array $context = array())
    {
        return array('success' => true);
    }
}

class StubSavedCardRecurring
{
    public $calls = array();

    public function remove_group_pricing($customerId, $productId = false)
    {
        $this->calls[] = array($customerId, $productId);
    }
}

function assert_true($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new Exception($message . ' Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true));
    }
}

try {
    if (!defined('DB_PREFIX')) {
        define('DB_PREFIX', '');
    }
    if (!defined('TABLE_PAYPAL_RECURRING')) {
        define('TABLE_PAYPAL_RECURRING', 'paypal_recurring');
    }

    $db = new FakeDB();
    $GLOBALS['db'] = $db;

    $customerId = 321;
    $profileId = 'I-UNITTEST';

    $subscription = array(
        'subscription_id' => 12,
        'customers_id' => $customerId,
        'profile_id' => $profileId,
        'products_id' => 44,
        'preferred_gateway' => 'legacy',
        'profile_source' => 'legacy',
    );

    $gateway = new StubGateway();
    $manager = new PayPalProfileManager(array($gateway));
    $savedCard = new StubSavedCardRecurring();

    $result = zen_paypal_subscription_cancel_immediately($customerId, $profileId, array(
        'note' => 'Cancel test',
        'source' => 'unit',
        'subscription' => $subscription,
        'profile_manager' => $manager,
        'saved_card_recurring' => $savedCard,
    ));

    assert_true(!empty($result['success']), 'Cancellation should succeed.');
    assert_same('', $result['message'], 'No error message expected on success.');
    assert_same(1, count($gateway->cancelCalls), 'Gateway cancel should be invoked once.');

    $call = $gateway->cancelCalls[0];
    assert_same($subscription, $call['subscription'], 'Subscription context should be forwarded to gateway.');
    assert_same('Cancel test', $call['note'], 'Cancel note should be forwarded.');
    assert_true(isset($call['context']['operation']) && $call['context']['operation'] === 'cancel', 'Operation context should be cancel.');
    assert_true(isset($call['context']['source']) && $call['context']['source'] === 'unit', 'Source context should be propagated.');
    assert_true(isset($call['context']['preferred_gateway']) && $call['context']['preferred_gateway'] === 'legacy', 'Preferred gateway should be resolved.');

    $updateFound = false;
    $deleteFound = false;
    foreach ($db->queries as $sql) {
        if (strpos($sql, "UPDATE " . TABLE_PAYPAL_RECURRING . "   SET status = 'Cancelled'") !== false) {
            $updateFound = true;
        }
        if (strpos($sql, 'DELETE FROM ' . zen_paypal_subscription_cache_table_name()) !== false) {
            $deleteFound = true;
        }
    }

    assert_true($updateFound, 'Subscription status update should be executed.');
    assert_true($deleteFound, 'Cache invalidation should run.');
    assert_same(array(array($customerId, 44)), $savedCard->calls, 'Group pricing removal should be invoked.');

    echo "All integration assertions passed.\n";
    exit(0);
} catch (Exception $exception) {
    fwrite(STDERR, 'Test failure: ' . $exception->getMessage() . "\n");
    exit(1);
}
