<?php
/**
 * Stacked wallet layout: detect #alt_pmnt_methods / stacked class in JS+CSS.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout';
$js = file_get_contents($root . '/jquery.paypalac.wallet_layout.js');
$css = file_get_contents($root . '/paypalac.css');

$failed = false;
$checks = [
    'js' => [
        'isStackedWalletLayout' => 'stacked detector function',
        "getElementById('alt_pmnt_methods')" => 'alt_pmnt_methods detection',
        'paypalac-wallet-layout-stacked' => 'stacked class marker',
        'hideWalletRadioForStacked' => 'stacked radio hide helper',
        'paypalac-wallet-payment-row-stacked' => 'stacked row class',
    ],
    'css' => [
        'paypalac-wallet-layout-stacked' => 'stacked CSS host',
        '#alt_pmnt_methods' => 'separator styles',
    ],
];

foreach ($checks['js'] as $needle => $label) {
    if (strpos((string)$js, $needle) === false) {
        fwrite(STDERR, "✗ JS missing $label ($needle)\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ JS $label\n");
    }
}
foreach ($checks['css'] as $needle => $label) {
    if (strpos((string)$css, $needle) === false) {
        fwrite(STDERR, "✗ CSS missing $label ($needle)\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ CSS $label\n");
    }
}

$theme = dirname(__DIR__) . '/extras/sts_next_level/css/checkout_one.css';
if (!is_file($theme)) {
    fwrite(STDERR, "✗ Missing extras/sts_next_level/css/checkout_one.css\n");
    $failed = true;
} else {
    fwrite(STDOUT, "✓ Theme checkout_one.css package present\n");
}

exit($failed ? 1 : 0);
