<?php
/**
 * Guard: wallet-only checkout must not show rogue "PayPal" text.
 *
 * The SDK still probes #ppr-choice-paypal .ppr-choice-label, but that sentinel
 * must be visually hidden and must not wrap/replace the branded button image
 * (OPRC, One Page Checkout, and Zen Cart default checkout).
 */

$jsFile = __DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.checkout.js';
$cssFile = __DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/paypalac.css';

$testPassed = true;
$errors = [];

$js = file_get_contents($jsFile);
$css = file_get_contents($cssFile);

if ($js === false || $css === false) {
    fwrite(STDERR, "FAIL: unable to read checkout.js / paypalac.css\n");
    exit(1);
}

if (strpos($js, 'function applySentinelVisuallyHidden') === false) {
    $testPassed = false;
    $errors[] = 'checkout.js must define applySentinelVisuallyHidden() for the SDK probe.';
}

if (strpos($js, 'while (paymentLabel.firstChild)') !== false) {
    $testPassed = false;
    $errors[] = 'checkout.js must not wrap payment-label children into the sentinel (wipes external-script + img layouts).';
}

if (strpos($js, "label.textContent = 'PayPal'") === false) {
    $testPassed = false;
    $errors[] = 'checkout.js must still populate probe text "PayPal" for the SDK.';
}

if (strpos($js, 'label[for="pmt-paypalac"] img') === false) {
    $testPassed = false;
    $errors[] = 'click handler must prefer the branded PayPal img over the sentinel.';
}

if (strpos($js, "jQuery('#ppr-choice-paypal .ppr-choice-label')") !== false
    && !preg_match('/\.ppr-button-choice #ppr-choice-paypal \.ppr-choice-label/', $js)
) {
    $testPassed = false;
    $errors[] = 'click handler must not bind wallet clicks to the bare sentinel selector.';
}

if (strpos($css, '.paypalac-ppr-choice-sentinel') === false) {
    $testPassed = false;
    $errors[] = 'paypalac.css must hide .paypalac-ppr-choice-sentinel.';
}

if ($testPassed) {
    echo "✓ PayPal choice sentinel stays visually hidden and does not steal the branded button\n";
    exit(0);
}

fwrite(STDERR, "FAIL: PayPal choice sentinel UI regressions\n");
foreach ($errors as $error) {
    fwrite(STDERR, "  - {$error}\n");
}
exit(1);
