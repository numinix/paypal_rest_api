<?php
/**
 * Pay Later min/max amount limits and SDK isolation.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

declare(strict_types=1);

$failures = 0;

$paylaterPhp = file_get_contents(__DIR__ . '/../includes/modules/payment/paypalac_paylater.php');
$paylaterJs = file_get_contents(__DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.paylater.js');
$walletPhp = file_get_contents(__DIR__ . '/../ppac_wallet.php');
$langPhp = file_get_contents(__DIR__ . '/../includes/languages/english/modules/payment/lang.paypalac_paylater.php');

fwrite(STDOUT, "Testing Pay Later amount limits\n");
fwrite(STDOUT, "================================\n\n");

if (strpos($paylaterPhp, "protected const CURRENT_VERSION = '1.1.0'") === false) {
    fwrite(STDERR, "FAIL: paypalac_paylater version should be 1.1.0 so tableCheckup inserts the new amount keys\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Pay Later module version is 1.1.0\n");
}

foreach ([
    'MODULE_PAYMENT_PAYPALAC_PAYLATER_MIN_AMOUNT',
    'MODULE_PAYMENT_PAYPALAC_PAYLATER_MAX_AMOUNT',
] as $key) {
    if (strpos($paylaterPhp, $key) === false) {
        fwrite(STDERR, "FAIL: paypalac_paylater.php should define $key\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  ✓ $key is present\n");
    }
}

if (strpos($paylaterPhp, "DEFAULT_MAX_AMOUNT = 2000.0") === false) {
    fwrite(STDERR, "FAIL: default Pay Later maximum should be 2000\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Default maximum amount is 2000\n");
}

if (strpos($paylaterPhp, "DEFAULT_MIN_AMOUNT = 30.0") === false) {
    fwrite(STDERR, "FAIL: default Pay Later minimum should be 30\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Default minimum amount is 30\n");
}

if (strpos($paylaterPhp, 'isOrderTotalWithinPayLaterLimits') === false
    || strpos($paylaterPhp, 'orderTotalWithinConfiguredLimits') === false
) {
    fwrite(STDERR, "FAIL: paypalac_paylater.php should enforce configured amount limits\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Amount limit helpers are present\n");
}

if (strpos($paylaterPhp, "'MODULE_PAYMENT_PAYPALAC_PAYLATER_MIN_AMOUNT'") === false
    || strpos($paylaterPhp, "'MODULE_PAYMENT_PAYPALAC_PAYLATER_MAX_AMOUNT'") === false
) {
    fwrite(STDERR, "FAIL: keys() should expose the min/max amount settings\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Admin keys() includes min/max amount settings\n");
}

if (strpos($paylaterPhp, 'reason') === false || strpos($paylaterPhp, 'order_creation_failed') === false) {
    fwrite(STDERR, "FAIL: wallet order creation failures should return a reason and message\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Wallet order creation failures return a reason\n");
}

if (strpos($langPhp, 'MODULE_PAYMENT_PAYPALAC_PAYLATER_ERROR_BELOW_MINIMUM') === false
    || strpos($langPhp, 'MODULE_PAYMENT_PAYPALAC_PAYLATER_ERROR_ABOVE_MAXIMUM') === false
) {
    fwrite(STDERR, "FAIL: language file should include min/max amount error strings\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Language strings for amount limits are present\n");
}

if (strpos($walletPhp, '$wallet === \'paylater\'') === false) {
    fwrite(STDERR, "FAIL: ppac_wallet.php should initialize order totals for Pay Later config_only requests\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Pay Later config_only requests initialize order totals\n");
}

if (strpos($paylaterJs, 'function shouldHidePayLaterForConfig') === false
    || strpos($paylaterJs, 'function isWithinPayLaterLimits') === false
) {
    fwrite(STDERR, "FAIL: Pay Later JS should hide the button outside configured amount limits\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Pay Later JS has client-side amount limit checks\n");
}

if (strpos($paylaterJs, 'data-namespace') === false
    || strpos($paylaterJs, 'paypalacPaylater') === false
    || strpos($paylaterJs, "dataset.paypalSdk = 'paylater'") === false
) {
    fwrite(STDERR, "FAIL: Pay Later JS should load a dedicated SDK namespace instead of removing #PayPalJSSDK\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Pay Later JS loads a dedicated paypalacPaylater SDK namespace\n");
}

if (preg_match('/existingScript\.parentNode\.removeChild\(existingScript\)/', $paylaterJs)) {
    fwrite(STDERR, "FAIL: Pay Later JS should not remove the shared header PayPal SDK script\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Pay Later JS does not remove the header PayPal SDK\n");
}

if (strpos($paylaterJs, 'enable-funding=paylater') === false) {
    fwrite(STDERR, "FAIL: Pay Later JS should still request enable-funding=paylater\n");
    $failures++;
} else {
    fwrite(STDOUT, "  ✓ Pay Later JS still enables Pay Later funding\n");
}

if ($failures > 0) {
    fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\n✓ Pay Later amount limits test passed.\n");
