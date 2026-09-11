<?php
/**
 * ppac_wallet.php constructs wallet modules outside Zen Cart's payment loader.
 * PHP 8+ fatals on bare undefined language constants; ?? does not protect them.
 */
declare(strict_types=1);

$root = dirname(__DIR__) . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0';
$files = [
    $root . '/catalog/includes/modules/payment/paypalac_applepay.php' => 'MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_TITLE',
    $root . '/catalog/includes/modules/payment/paypalac_googlepay.php' => 'MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_TEXT_TITLE',
    $root . '/catalog/includes/modules/payment/paypalac_paylater.php' => 'MODULE_PAYMENT_PAYPALAC_PAYLATER_TEXT_TITLE',
    $root . '/catalog/includes/modules/payment/paypalac_venmo.php' => 'MODULE_PAYMENT_PAYPALAC_VENMO_TEXT_TITLE',
];

$failed = false;
foreach ($files as $path => $constant) {
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read $path\n");
        $failed = true;
        continue;
    }

    $guard = "defined('$constant')";
    if (strpos($content, $guard) === false) {
        fwrite(STDERR, "✗ Missing $guard in " . basename($path) . "\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ " . basename($path) . " guards $constant\n");
    }

    // Regression: bare constant + ?? must not remain for storefront title assignment.
    if (preg_match('/\$this->title\s*=\s*' . preg_quote($constant, '/') . '\s*\?\?/', $content)) {
        fwrite(STDERR, "✗ Unsafe $constant ?? title pattern still present in " . basename($path) . "\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ No unsafe $constant ?? title pattern in " . basename($path) . "\n");
    }
}

$walletEntrypoints = [
    $root . '/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/ppac_wallet.php',
    $root . '/Installer/assets/root/ppac_wallet.php',
];
foreach ($walletEntrypoints as $path) {
    $content = file_get_contents($path);
    $rel = str_replace('\\', '/', substr($path, strlen($root)));
    if ($content === false) {
        fwrite(STDERR, "Unable to read $path\n");
        $failed = true;
        continue;
    }
    if (strpos($content, "languages/' . \$ppacWalletLanguage . '/modules/payment/lang.' . \$moduleCode . '.php'") === false) {
        fwrite(STDERR, "✗ Missing language candidate path in $rel\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ Language preload present in $rel\n");
    }
    if (strpos($content, 'catch (\\Throwable $e)') === false) {
        fwrite(STDERR, "✗ Missing Throwable catch in $rel\n");
        $failed = true;
    } else {
        fwrite(STDOUT, "✓ Throwable catch present in $rel\n");
    }
}

exit($failed ? 1 : 0);
