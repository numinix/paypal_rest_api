<?php
declare(strict_types=1);

if (!defined('IS_ADMIN_FLAG')) {
    define('IS_ADMIN_FLAG', true);
}

require_once __DIR__ . '/../includes/modules/payment/paypal/PayPalRestful/Compatibility/StoreCredit.php';

use PayPalRestful\Compatibility\StoreCredit;

$fixtureDir = sys_get_temp_dir() . '/paypalr_store_credit_' . uniqid('', true);
if (!mkdir($fixtureDir) && !is_dir($fixtureDir)) {
    fwrite(STDERR, "Unable to create fixture directory.\n");
    exit(1);
}

register_shutdown_function(static function () use ($fixtureDir): void {
    if (is_dir($fixtureDir)) {
        array_map('unlink', glob($fixtureDir . '/*') ?: []);
        rmdir($fixtureDir);
    }
});

$fixturePath = $fixtureDir . '/ot_sc.php';
$original = <<<'PHP'
<?php
class ot_sc
{
    public function apply_credit()
    {
        $this->customer_credit->fields['amount'] = 0;
        $this->another_query->fields['status'] = 'used';
    }
}
PHP;

file_put_contents($fixturePath, $original);

StoreCredit::ensureSafeApplyCredit($fixturePath);
$patched = file_get_contents($fixturePath);

if (strpos($patched, 'paypalr store-credit null guard') === false) {
    fwrite(STDERR, "Guard marker missing from patched store credit module.\n");
    exit(1);
}

$hashBefore = md5($patched);
StoreCredit::ensureSafeApplyCredit($fixturePath);
$hashAfter = md5((string)file_get_contents($fixturePath));

if ($hashBefore !== $hashAfter) {
    fwrite(STDERR, "Store credit patcher is not idempotent.\n");
    exit(1);
}

fwrite(STDOUT, "Store credit patcher tests passed.\n");
