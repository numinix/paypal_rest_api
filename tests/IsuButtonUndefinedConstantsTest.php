<?php
/**
 * Admin Payment Modules instantiates paypalac before install() creates config keys.
 * PHP 8+ fatals on bare undefined-constant reads; ?? does not protect them.
 */
declare(strict_types=1);

$paypalac = dirname(__DIR__) . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypalac.php';
$content = file_get_contents($paypalac);
if ($content === false) {
    fwrite(STDERR, "Unable to read paypalac.php\n");
    exit(1);
}

$checks = [
    "defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_L')" => 'getIsuButton live client id guard',
    "defined('MODULE_PAYMENT_PAYPALAC_SECRET_L')" => 'getIsuButton live secret guard',
    "defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S')" => 'getIsuButton sandbox client id guard',
    "defined('MODULE_PAYMENT_PAYPALAC_SECRET_S')" => 'getIsuButton sandbox secret guard',
    "defined('MODULE_PAYMENT_PAYPALAC_TEXT_ADMIN_UPGRADE_AVAILABLE')" => 'upgrade template language guard',
];

$failed = false;
foreach ($checks as $needle => $label) {
    if (strpos($content, $needle) === false) {
        fwrite(STDERR, "✗ Missing $label ($needle)\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ $label\n");
    }
}

// Regression: bare constant + ?? must not return in getIsuButton credential reads.
if (preg_match('/trim\(\s*MODULE_PAYMENT_PAYPALAC_CLIENTID_L\s*\?\?/', $content)) {
    fwrite(STDERR, "✗ Unsafe CLIENTID_L ?? trim pattern still present\n");
    $failed = true;
} else {
    fwrite(STDOUT, "✓ No unsafe CLIENTID_L ?? trim pattern\n");
}

exit($failed ? 1 : 0);
