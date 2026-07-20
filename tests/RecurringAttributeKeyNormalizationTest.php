<?php
/**
 * Verify recurring observer accepts both PayPal-prefixed and legacy attribute labels.
 */
declare(strict_types=1);

if (!defined('DIR_FS_CATALOG')) {
    define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
}
if (!defined('DIR_WS_MODULES')) {
    define('DIR_WS_MODULES', 'includes/modules/');
}

if (!isset($psr4Autoloader)) {
    $psr4Autoloader = new class {
        public function addPrefix(string $prefix, string $path): void
        {
        }
    };
}

require_once DIR_FS_CATALOG . 'includes/classes/observers/auto.paypaladvcheckout_recurring.php';

class RecurringObserverTestHarness extends zcObserverPaypaladvcheckoutRecurring
{
    public function publicNormalizeAttributeKey(string $label): string
    {
        return $this->normalizeAttributeKey($label);
    }

    /**
     * @param array<string,string> $attributeMap
     * @return array<string,mixed>|null
     */
    public function publicExtractSubscriptionAttributes(array $attributeMap): ?array
    {
        return $this->extractSubscriptionAttributes($attributeMap);
    }
}

function buildAttributeMap(RecurringObserverTestHarness $observer, array $labels): array
{
    $map = [];
    foreach ($labels as $label => $value) {
        $map[$observer->publicNormalizeAttributeKey($label)] = $value;
    }

    return $map;
}

function testPaypalPrefixedAttributes(): bool
{
    $observer = new RecurringObserverTestHarness();

    $attributeMap = buildAttributeMap($observer, [
        'Automatic Renewal' => 'Accept Automatic Renewal',
        'PayPal Subscription Plan ID' => 'PLAN-123',
        'PayPal Subscription Billing Period' => 'Month',
        'PayPal Subscription Billing Frequency' => '1',
        'PayPal Subscription Total Billing Cycles' => '12',
    ]);

    $result = $observer->publicExtractSubscriptionAttributes($attributeMap);
    if ($result === null) {
        fwrite(STDERR, "FAIL: PayPal-prefixed attributes were not accepted\n");
        return false;
    }

    if ($result['plan_id'] !== 'PLAN-123') {
        fwrite(STDERR, "FAIL: PayPal-prefixed plan_id was not preserved\n");
        return false;
    }

    fwrite(STDOUT, "  ✓ PayPal-prefixed attributes accepted and normalized\n");
    return true;
}

function testLegacyAttributeLabels(): bool
{
    $observer = new RecurringObserverTestHarness();

    $attributeMap = buildAttributeMap($observer, [
        'Automatic Renewal' => 'Accept Automatic Renewal',
        'Billing Period' => 'Week',
        'Billing Frequency' => '2',
        'Total Billing Cycles' => '6',
    ]);

    $result = $observer->publicExtractSubscriptionAttributes($attributeMap);
    if ($result === null) {
        fwrite(STDERR, "FAIL: Legacy attribute labels were not accepted\n");
        return false;
    }

    if ($result['billing_period'] !== 'WEEK' || $result['billing_frequency'] !== 2 || $result['total_billing_cycles'] !== 6) {
        fwrite(STDERR, "FAIL: Legacy attribute labels did not normalize correctly\n");
        return false;
    }

    fwrite(STDOUT, "  ✓ Legacy attribute labels accepted and normalized\n");
    return true;
}

function testMembershipTermWithoutAutomaticRenewal(): bool
{
    $observer = new RecurringObserverTestHarness();

    $attributeMap = buildAttributeMap($observer, [
        'Billing Period' => 'Yearly',
        'Billing Frequency' => '1',
        'Total Billing Cycles' => '1',
    ]);

    $result = $observer->publicExtractSubscriptionAttributes($attributeMap);
    if ($result !== null) {
        fwrite(STDERR, "FAIL: Membership term attributes without automatic renewal should not create a subscription\n");
        return false;
    }

    fwrite(STDOUT, "  ✓ Membership term without automatic renewal does not create subscription\n");
    return true;
}

function testIndefinitePlanWithNamedMonthlyFrequency(): bool
{
    $observer = new RecurringObserverTestHarness();

    $attributeMap = buildAttributeMap($observer, [
        'Billing Period' => 'Monthly',
        'Billing Frequency' => 'Monthly',
        'Total Billing Cycles' => 'Good until cancelled',
    ]);

    $result = $observer->publicExtractSubscriptionAttributes($attributeMap);
    if ($result === null) {
        fwrite(STDERR, "FAIL: Indefinite plan with Billing Frequency=Monthly should create a subscription\n");
        return false;
    }

    if ($result['billing_period'] !== 'MONTH' || (int) $result['billing_frequency'] !== 1 || (int) $result['total_billing_cycles'] !== 0) {
        fwrite(STDERR, "FAIL: Monthly frequency label did not normalize correctly: " . json_encode($result) . "\n");
        return false;
    }

    fwrite(STDOUT, "  ✓ Indefinite plan with named Monthly frequency accepted\n");
    return true;
}

echo "\n=== Testing Recurring Attribute Label Normalization ===\n\n";

$failures = 0;

echo "Test 1: PayPal-prefixed attribute labels...\n";
if (!testPaypalPrefixedAttributes()) {
    $failures++;
}

echo "\nTest 2: Legacy attribute labels...\n";
if (!testLegacyAttributeLabels()) {
    $failures++;
}

echo "\nTest 3: Membership term without automatic renewal...\n";
if (!testMembershipTermWithoutAutomaticRenewal()) {
    $failures++;
}

echo "\nTest 4: Indefinite plan with Billing Frequency=Monthly...\n";
if (!testIndefinitePlanWithNamedMonthlyFrequency()) {
    $failures++;
}

if ($failures > 0) {
    fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\n✓ All recurring attribute normalization tests passed!\n");
exit(0);
